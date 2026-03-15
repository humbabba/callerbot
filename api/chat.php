<?php

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');

// Disable output buffering for real-time SSE
while (ob_get_level()) ob_end_clean();

function emit(string $event, array $data): void {
    echo "event: {$event}\n";
    echo 'data: ' . json_encode($data) . "\n\n";
    flush();
}

function emitStatus(string $text): void {
    emit('status', ['text' => $text]);
}

function emitDone(array $data, string $userInput = ''): void {
    if ($userInput !== '') {
        $output = $data['reply'] ?? $data['error'] ?? '';
        logQuery($userInput, $data['model'] ?? null, $data['function'] ?? null, $output);
    }
    emit('done', $data);
}

function emitError(string $errorMessage): void {
    emit('done', ['error' => $errorMessage]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    emitError('Method not allowed');
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$message = trim($input['message'] ?? '');

if ($message === '') {
    http_response_code(400);
    emitError('Message is required');
    exit;
}

require __DIR__ . '/functions.php';
require __DIR__ . '/../db/database.php';

$config = require __DIR__ . '/../config/config.php';
$apiKey = $config['groq_api_key'];
$models = $config['models'];

// Build messages array (OpenAI format)
$history = $input['history'] ?? [];
$messages = [
    ['role' => 'system', 'content' => 'You are Callerbot, a helpful assistant with a hacker aesthetic. Be concise.

You have ONLY these tools available: get_weather, get_forecast, sunrise_sunset, country_intel, word_lookup, currency_convert, travel_advisory, tourist_guide, local_time, public_holidays.
NEVER attempt to call any tool not in that list.
NEVER invent or add parameters that are not defined in a tool\'s schema. Only use the exact parameters each tool declares.
NEVER use placeholders like [insert X] or [date from results]. Always use the actual data returned by tools. If a tool did not return the information needed, say so honestly.

When a user request matches one of your tools, call it. Use conversation history to fill in missing parameters — for example, if the user previously asked about Tokyo and then says "what\'s the weather like?", call get_weather with city "Tokyo". Always prefer calling an existing tool with inferred context over proposing a new function.

CRITICAL: Resolve vague references like "local currency", "their money", "the capital", "there", etc. using conversation history. If the conversation is about Thailand and the user says "how much local money can I get for $500", call currency_convert with from="USD", to="THB", amount=500. Always resolve to concrete parameter values — never pass descriptions or vague text as arguments.

FORMATTING: Your responses are displayed as plain text in a terminal UI. Do NOT use markdown tables, HTML tags, or <br>. Use short plain-text lists with dashes or bullet points. Keep responses concise and scannable.

TOOL ERRORS: If a tool call fails or returns an error, try a DIFFERENT existing tool that might answer the question. For example, if travel_advisory fails for a country, call country_intel instead — it returns region, population, and other context the user may find useful. Only propose a new function if NO existing tool can help at all.

NEVER propose a function that duplicates or overlaps with an existing tool. If an existing tool can handle the request (even with parameters inferred from context), call it instead of proposing a new one. travel_warning, safety_check, etc. overlap with travel_advisory — do NOT propose these.

When NO existing tool fits the request, follow this decision tree:

1. Is the question related to travel research (destinations, transport, visas, packing, culture, food, accommodation, activities, etc.)?
   - YES: Think about whether a free, public API or data source exists that could answer it (e.g. open government data, OpenStreetMap, Wikipedia, free REST APIs). If you can identify a real, free data source, propose a new tool:

[FUNCTION PROPOSAL]
Name: suggested_function_name
Description: What it would do (general purpose, not specific to this one query)
Parameters:
- param_name (type): description
Returns: What it would return
Data source: The specific free API or data source this would use (must be real and free)
Example: How this function would handle the current request

   - If you CANNOT identify a real free data source, respond naturally. Acknowledge what the user is asking and let them know it falls outside your current toolset. Briefly mention what you CAN help with (weather, forecasts, country info, currency conversion, travel advisories, destination guides, local time, holidays, word definitions).

2. Is the question NOT related to travel research?
   - Respond naturally and conversationally. Let the user know you are a travel research assistant and briefly mention the kinds of things you can help with. Be friendly, not robotic.'],
];
foreach ($history as $turn) {
    $role = $turn['role'] === 'model' ? 'assistant' : $turn['role'];
    $messages[] = ['role' => $role, 'content' => $turn['text']];
}
$messages[] = ['role' => 'user', 'content' => $message];

$tools = getOpenAIToolDeclarations();

// Try each model until one works
$model = null;
$result = null;

emitStatus('Routing to inference engine...');

foreach ($models as $candidate) {
    emitStatus("Connecting to {$candidate}...");
    $result = callGroq($apiKey, $candidate, $messages, $tools);

    if (!isset($result['error'])) {
        $model = $candidate;
        break;
    }

    if (!isQuotaError($result['error'])) {
        $friendly = friendlyError($result['error']);
        if ($friendly === null) {
            // Schema validation error — retry without tools so the model can clarify
            break;
        }
        emitError($friendly);
        exit;
    }

    emitStatus("Rate limited on {$candidate}, trying fallback...");
}

if (!$model) {
    emitError('Callerbot has hit its daily usage limit. Please check back in a little while — the limit resets automatically.');
    exit;
}

emitStatus("Model locked: {$model}");

// If the model selection loop broke due to a validation error, retry without tools
if (isset($result['error'])) {
    emitStatus('Schema mismatch — retrying without tools...');
    $result = callGroq($apiKey, $model, $messages, []);
    if (isset($result['error'])) {
        emitError(friendlyError($result['error']) ?? 'Something went wrong. Please try again in a moment.');
        exit;
    }
    $choice = $result['choices'][0] ?? [];
    emitDone([
        'reply' => $choice['message']['content'] ?? '',
        'model' => $model,
    ], $message);
    exit;
}

// Function calling loop
$maxRounds = 5;
$activeTools = $tools;
$calledFunction = null;

for ($round = 0; $round < $maxRounds; $round++) {
    if (!$result) {
        emitStatus($round === 0 ? 'Evaluating tool candidates...' : 'Re-evaluating with new context...');
        $result = callGroq($apiKey, $model, $messages, $activeTools);
        if (isset($result['error'])) {
            $friendly = friendlyError($result['error']);
            if ($friendly === null && !empty($activeTools)) {
                emitStatus('Schema mismatch — retrying without tools...');
                $activeTools = [];
                $result = null;
                continue;
            }
            emitError($friendly ?? 'Something went wrong. Please try again in a moment.');
            exit;
        }
    }

    $choice = $result['choices'][0] ?? [];
    $assistantMessage = $choice['message'] ?? [];
    $toolCalls = $assistantMessage['tool_calls'] ?? [];

    if (empty($toolCalls)) {
        if ($calledFunction) {
            emitStatus('Composing response from tool data...');
        } else {
            emitStatus('No matching tool — composing direct response...');
        }

        $response = [
            'reply' => $assistantMessage['content'] ?? '',
            'model' => $model,
        ];
        if ($calledFunction) {
            $response['function'] = $calledFunction;
        }
        emitDone($response, $message);
        exit;
    }

    // Append the assistant's message (with tool calls) to history
    $messages[] = $assistantMessage;

    // Execute each tool call and append results
    $anyToolFailed = false;
    foreach ($toolCalls as $toolCall) {
        $fnName = $toolCall['function']['name'];
        $fnArgs = json_decode($toolCall['function']['arguments'], true) ?? [];
        $argSummary = implode(', ', array_map(fn($k, $v) => "{$k}=\"{$v}\"", array_keys($fnArgs), $fnArgs));

        emitStatus("Tool selected: {$fnName}({$argSummary})");
        $calledFunction = $fnName;

        emitStatus("Executing {$fnName}...");
        $fnResult = executeFunction($fnName, $fnArgs);

        $decoded = json_decode($fnResult, true);
        if (isset($decoded['error'])) {
            $anyToolFailed = true;
            emitStatus("{$fnName} returned error — will try fallback...");
        } else {
            emitStatus("{$fnName} returned data — sending to model...");
        }

        $messages[] = [
            'role'        => 'tool',
            'tool_call_id' => $toolCall['id'],
            'content'     => $fnResult,
        ];
    }

    // Keep tools available if a tool returned an error, so the model can try a different one
    if (!$anyToolFailed) {
        $activeTools = [];
    }
    $result = null;
}

emitError('Something went wrong processing that request. Try rephrasing or ask something else.');

function friendlyError(string $raw): ?string {
    $lower = strtolower($raw);

    if (isQuotaError($raw)) {
        return 'Callerbot has hit its usage limit. Please check back in a little while — the limit resets automatically.';
    }

    if (str_contains($lower, 'timeout') || str_contains($lower, 'timed out')) {
        return 'The request timed out. Please try again in a moment.';
    }

    if (str_contains($lower, 'validation failed') || str_contains($lower, 'did not match schema')) {
        return null; // Signal to retry without tools
    }

    if (str_contains($lower, 'decommissioned') || str_contains($lower, 'no longer supported')) {
        return 'Callerbot is temporarily unavailable due to a configuration issue. Please try again later.';
    }

    return 'Something went wrong. Please try again in a moment.';
}

function isQuotaError(string $errorMessage): bool {
    $patterns = ['quota', 'rate limit', 'rate_limit', 'resource exhausted', '429', 'too many requests'];
    $lower = strtolower($errorMessage);
    foreach ($patterns as $p) {
        if (str_contains($lower, $p)) {
            return true;
        }
    }
    return false;
}

function callGroq(string $apiKey, string $model, array $messages, array $tools): array {
    $url = 'https://api.groq.com/openai/v1/chat/completions';

    $payload = [
        'model'       => $model,
        'messages'    => $messages,
        'temperature' => 0.7,
        'max_tokens'  => 1024,
    ];

    if (!empty($tools)) {
        $payload['tools'] = $tools;
        $payload['tool_choice'] = 'auto';
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return ['error' => 'Failed to reach Groq API'];
    }

    if ($httpCode !== 200) {
        $body = json_decode($response, true);
        return ['error' => $body['error']['message'] ?? 'Groq API error (HTTP ' . $httpCode . ')'];
    }

    return json_decode($response, true);
}
