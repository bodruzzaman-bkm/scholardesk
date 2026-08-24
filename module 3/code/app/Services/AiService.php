<?php

namespace App\Services;

use App\Exceptions\AiUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin client for the configured language-model provider.
 *
 * Two providers are supported and the driver is picked from config:
 *
 *   groq   - OpenAI-compatible /chat/completions. Free tier, fast inference,
 *            chat only (no embeddings, which does not matter here because
 *            EmbeddingService runs locally).
 *   gemini - Google Generative Language API.
 *
 * Every provider call in the app funnels through generate(), so the provider
 * is swappable and the whole AI layer can be faked in tests.
 *
 * Deliberate design point from ScholarDesk: AI is an enhancement, never a hard
 * dependency. With no key configured isConfigured() returns false and callers
 * show a clear "not configured" state; the library, reader, notes, search,
 * export and collaboration features are entirely unaffected.
 */
class AiService
{
    /**
     * Roughly four characters per token for English prose. Used only to keep
     * requests inside the provider's per-minute budget, so an approximation
     * with headroom is sufficient.
     */
    private const CHARS_PER_TOKEN = 4;

    public function __construct(
        private ?string $provider = null,
        private ?string $apiKey = null,
        private ?string $model = null,
        private int $timeout = 60,
    ) {
        $this->provider ??= config('services.ai.provider', 'groq');
        $this->apiKey ??= config("services.{$this->provider}.key");
        $this->model ??= config("services.{$this->provider}.model");
    }

    /**
     * Characters of prompt this provider can accept in one request.
     *
     * Providers meter *tokens per minute*, and the reserved completion budget
     * counts towards it, so the prompt allowance is the total budget minus the
     * completion reserve. Groq's free tier is 8k TPM, which a full-paper
     * prompt exceeds easily — hence the explicit budget rather than sending
     * everything and hoping.
     */
    public function promptCharBudget(): int
    {
        $tokenBudget = (int) config('services.ai.token_budget', 8000);
        $completion = $this->maxCompletionTokens();

        // 15% headroom for the system instruction, message envelope and the
        // fact that four-chars-per-token under-counts on dense academic text.
        $promptTokens = (int) max(500, ($tokenBudget - $completion) * 0.85);

        return $promptTokens * self::CHARS_PER_TOKEN;
    }

    private function maxCompletionTokens(): int
    {
        return (int) config('services.ai.max_output_tokens', 2048);
    }

    public function isConfigured(): bool
    {
        return filled($this->apiKey);
    }

    public function provider(): string
    {
        return (string) $this->provider;
    }

    public function model(): ?string
    {
        return $this->model;
    }

    /**
     * Send a prompt and return the model's text.
     *
     * @throws AiUnavailableException on any failure, so callers have exactly
     *                                one error type to handle.
     */
    public function generate(string $prompt, string $systemInstruction = ''): string
    {
        if (! $this->isConfigured()) {
            throw new AiUnavailableException(
                'The AI assistant is not configured. Add an API key to your .env to enable it.'
            );
        }

        $response = $this->provider === 'gemini'
            ? $this->callGemini($prompt, $systemInstruction)
            : $this->callGroq($prompt, $systemInstruction);

        $this->assertUsable($response);

        $text = $this->provider === 'gemini'
            ? $response->json('candidates.0.content.parts.0.text')
            : $response->json('choices.0.message.content');

        if (! is_string($text) || trim($text) === '') {
            // Reasoning models spend output tokens before emitting content, so
            // an empty body usually means the budget ran out rather than a
            // refusal. Say something actionable either way.
            throw new AiUnavailableException(
                'The assistant returned an empty answer. Try a shorter question or a smaller collection.'
            );
        }

        return trim($text);
    }

    /** OpenAI-compatible chat completions (Groq). */
    private function callGroq(string $prompt, string $systemInstruction): Response
    {
        $messages = [];

        if ($systemInstruction !== '') {
            $messages[] = ['role' => 'system', 'content' => $systemInstruction];
        }

        $messages[] = ['role' => 'user', 'content' => $prompt];

        return $this->send('https://api.groq.com/openai/v1/chat/completions', [
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => 0.2,   // grounded, not creative
            /*
             | The gpt-oss models emit internal reasoning tokens before the
             | answer, so this cannot be tiny or the completion comes back with
             | an empty `content` and finish_reason "length". It also counts
             | towards the per-minute token budget, which is why the prompt
             | allowance above subtracts it.
             */
            'max_tokens' => $this->maxCompletionTokens(),
        ], ['Authorization' => 'Bearer '.$this->apiKey]);
    }

    private function callGemini(string $prompt, string $systemInstruction): Response
    {
        $payload = [
            'contents' => [[
                'role' => 'user',
                'parts' => [['text' => $prompt]],
            ]],
            'generationConfig' => [
                'temperature' => 0.2,
                'maxOutputTokens' => 2048,
            ],
        ];

        if ($systemInstruction !== '') {
            $payload['systemInstruction'] = ['parts' => [['text' => $systemInstruction]]];
        }

        return $this->send(
            sprintf('https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent', $this->model),
            $payload,
            ['x-goog-api-key' => $this->apiKey],
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    private function send(string $endpoint, array $payload, array $headers): Response
    {
        try {
            return Http::timeout($this->timeout)
                ->retry(2, 500, throw: false)
                ->withHeaders($headers)
                ->post($endpoint, $payload);
        } catch (ConnectionException $e) {
            Log::warning('AI provider unreachable', [
                'provider' => $this->provider,
                'error' => $e->getMessage(),
            ]);

            throw new AiUnavailableException('Could not reach the AI service. Try again in a moment.');
        }
    }

    private function assertUsable(Response $response): void
    {
        if ($response->status() === 429) {
            throw new AiUnavailableException('The assistant is busy (rate limited). Try again in a moment.');
        }

        /*
         | Groq reports "tokens per minute exceeded" as 413, not 429. Treating
         | it as a generic failure hid the real cause behind "the AI service
         | returned an error", so it is matched explicitly and reported as what
         | it is: a request that was too big for the current plan.
         */
        if ($response->status() === 413 || $response->json('error.code') === 'rate_limit_exceeded') {
            throw new AiUnavailableException(
                'That request was too large for the current AI plan\'s per-minute token budget. '
                .'Try a narrower question, or a collection with fewer papers.'
            );
        }

        if ($response->status() === 401 || $response->status() === 403) {
            Log::warning('AI provider rejected the credentials', ['provider' => $this->provider]);

            throw new AiUnavailableException('The AI API key was rejected. Check the key in your .env.');
        }

        if (! $response->successful()) {
            Log::warning('AI provider error', [
                'provider' => $this->provider,
                'status' => $response->status(),
                // Truncated: provider errors can echo back the whole prompt.
                'body' => mb_substr($response->body(), 0, 500),
            ]);

            throw new AiUnavailableException('The AI service returned an error. Your library is unaffected.');
        }
    }
}
