<?php

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$message = trim($input['message'] ?? '');

if ($message === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Message is required']);
    exit;
}

require __DIR__ . '/functions.php';

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

FORMATTING: Your responses are displayed as plain text in a terminal UI. Do NOT use markdown tables, HTML tags, or <br>. Use short plain-text lists with dashes or bullet points. Keep responses concise and scannable.

NEVER propose a function that duplicates an existing tool. If an existing tool can handle the request (even with parameters inferred from context), call it instead of proposing a new one.

When NO existing tool fits the request, DO NOT answer the question directly. Instead, propose a new GENERAL-PURPOSE function that could handle this request AND a broad category of similar requests. Think about the abstract category the request falls into — not the specific item. For example, if someone asks about a fictional weapon, propose a general "fictional item lookup" tool, not a weapon-specific one. If someone asks about a recipe, propose a general "recipe search" tool, not one for the specific dish.

Format your response exactly like this:

[FUNCTION PROPOSAL]
Name: suggested_function_name
Description: What it would do (general purpose, not specific to this one query)
Parameters:
- param_name (type): description
- param_name (type): description
Returns: What it would return
Example: How this function would handle the current request
Rationale: Why this general-purpose function would be useful across many scenarios

This way every interaction demonstrates function calling — either by executing a tool or by proposing one.'],
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

foreach ($models as $candidate) {
    $result = callGroq($apiKey, $candidate, $messages, $tools);

    if (!isset($result['error'])) {
        $model = $candidate;
        break;
    }

    if (!isQuotaError($result['error'])) {
        echo json_encode(['error' => friendlyError($result['error'])]);
        exit;
    }
}

if (!$model) {
    echo json_encode(['error' => 'Callerbot has hit its daily usage limit. Please check back in a little while — the limit resets automatically.']);
    exit;
}

// Function calling loop
$maxRounds = 5;
$activeTools = $tools;
$calledFunction = null;

for ($round = 0; $round < $maxRounds; $round++) {
    if (!$result) {
        $result = callGroq($apiKey, $model, $messages, $activeTools);
        if (isset($result['error'])) {
            echo json_encode(['error' => friendlyError($result['error'])]);
            exit;
        }
    }

    $choice = $result['choices'][0] ?? [];
    $assistantMessage = $choice['message'] ?? [];
    $toolCalls = $assistantMessage['tool_calls'] ?? [];

    if (empty($toolCalls)) {
        // No tool calls — return the text reply
        $response = [
            'reply' => $assistantMessage['content'] ?? '',
            'model' => $model,
        ];
        if ($calledFunction) {
            $response['function'] = $calledFunction;
        }
        echo json_encode($response);
        exit;
    }

    // Append the assistant's message (with tool calls) to history
    $messages[] = $assistantMessage;

    // Execute each tool call and append results
    foreach ($toolCalls as $toolCall) {
        $fnName = $toolCall['function']['name'];
        $fnArgs = json_decode($toolCall['function']['arguments'], true) ?? [];
        $fnResult = executeFunction($fnName, $fnArgs);
        $calledFunction = $fnName;

        $messages[] = [
            'role'        => 'tool',
            'tool_call_id' => $toolCall['id'],
            'content'     => $fnResult,
        ];
    }

    // After first tool execution, stop offering tools so the model summarizes
    $activeTools = [];
    $result = null;
}

echo json_encode(['error' => 'Something went wrong processing that request. Try rephrasing or ask something else.']);

function friendlyError(string $raw): string {
    $lower = strtolower($raw);

    if (isQuotaError($raw)) {
        return 'Callerbot has hit its usage limit. Please check back in a little while — the limit resets automatically.';
    }

    if (str_contains($lower, 'timeout') || str_contains($lower, 'timed out')) {
        return 'The request timed out. Please try again in a moment.';
    }

    if (str_contains($lower, 'validation failed') || str_contains($lower, 'did not match schema')) {
        return 'Callerbot had trouble understanding that request. Try rephrasing your question.';
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
