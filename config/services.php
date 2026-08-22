<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
     | Crossref asks callers to identify themselves; doing so puts requests in
     | the faster, more reliable "polite pool".
     */
    'crossref' => [
        'mailto' => env('CROSSREF_MAILTO', 'support@example.com'),
    ],

    /*
     | Language model for summaries, Q&A and literature-review drafts.
     |
     | Leave the key unset to run without AI: those features report "not
     | configured" and every other part of the app is unaffected. Semantic
     | search and related papers do NOT need a key at all — they use the local
     | embedding engine in App\Services\EmbeddingService.
     */
    'ai' => [
        // groq | gemini
        'provider' => env('AI_PROVIDER', 'groq'),

        /*
         | Providers meter tokens per minute, and the reserved completion
         | budget counts towards it. Groq's free tier allows 8,000 TPM, which
         | a whole-paper prompt exceeds — so the retrieved context is sized
         | from this figure rather than sent blindly. Raise it on a paid plan.
         */
        'token_budget' => (int) env('AI_TOKEN_BUDGET', 8000),
        'max_output_tokens' => (int) env('AI_MAX_OUTPUT_TOKENS', 2048),
    ],

    'groq' => [
        'key' => env('GROQ_API_KEY'),
        // Groq's OpenAI-compatible endpoint. gpt-oss-120b is the most capable
        // general chat model on the free tier.
        'model' => env('GROQ_CHAT_MODEL', 'openai/gpt-oss-120b'),
    ],

    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_CHAT_MODEL', 'gemini-2.0-flash'),
    ],

];
