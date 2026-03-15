<?php

return [
    'version' => '0.1.1',
    'groq_api_key' => getenv('GROQ_API_KEY') ?: 'your-groq-api-key-here',
    'models' => [
        'llama-3.3-70b-versatile',
        'openai/gpt-oss-20b',
    ],
];
