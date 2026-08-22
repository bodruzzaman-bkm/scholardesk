<?php

namespace Tests\Feature;

use App\Exceptions\AiUnavailableException;
use App\Services\AiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The provider driver: Groq (OpenAI-compatible) and Gemini.
 *
 * Provider responses are faked, so these never call a live API.
 */
class AiProviderTest extends TestCase
{
    use RefreshDatabase;

    private function groq(array $overrides = []): AiService
    {
        config(array_merge([
            'services.ai.provider' => 'groq',
            'services.groq.key' => 'test-key',
            'services.groq.model' => 'openai/gpt-oss-120b',
            'services.ai.token_budget' => 8000,
            'services.ai.max_output_tokens' => 2048,
        ], $overrides));

        return new AiService;
    }

    private function groqReply(string $text): array
    {
        return ['choices' => [['message' => ['role' => 'assistant', 'content' => $text]]]];
    }

    public function test_groq_is_called_with_an_openai_compatible_payload(): void
    {
        Http::fake(['api.groq.com/*' => Http::response($this->groqReply('Grounded answer.'), 200)]);

        $this->assertSame('Grounded answer.', $this->groq()->generate('Question?', 'Be careful.'));

        Http::assertSent(function ($request) {
            $body = $request->data();

            return str_contains($request->url(), '/openai/v1/chat/completions')
                && $request->hasHeader('Authorization', 'Bearer test-key')
                && $body['model'] === 'openai/gpt-oss-120b'
                // System instruction is sent as its own message.
                && $body['messages'][0]['role'] === 'system'
                && $body['messages'][1]['role'] === 'user';
        });
    }

    public function test_the_completion_reserve_is_sent_as_max_tokens(): void
    {
        Http::fake(['api.groq.com/*' => Http::response($this->groqReply('ok'), 200)]);

        $this->groq(['services.ai.max_output_tokens' => 1234])->generate('Hi');

        Http::assertSent(fn ($request) => $request->data()['max_tokens'] === 1234);
    }

    /**
     * The prompt allowance must exclude the completion reserve, because both
     * count towards the provider's tokens-per-minute limit.
     */
    public function test_the_prompt_budget_shrinks_with_a_smaller_token_allowance(): void
    {
        $generous = $this->groq(['services.ai.token_budget' => 30000])->promptCharBudget();
        $tight = $this->groq(['services.ai.token_budget' => 8000])->promptCharBudget();

        $this->assertGreaterThan($tight, $generous);

        // A tight budget must still leave room for a usable prompt.
        $this->assertGreaterThan(2000, $tight);
    }

    public function test_the_prompt_budget_never_goes_negative_on_a_tiny_allowance(): void
    {
        $budget = $this->groq([
            'services.ai.token_budget' => 500,
            'services.ai.max_output_tokens' => 2048,
        ])->promptCharBudget();

        $this->assertGreaterThan(0, $budget);
    }

    /**
     * Groq reports "tokens per minute exceeded" as 413, not 429. Reporting it
     * as a generic failure hid the real cause.
     */
    public function test_a_413_token_limit_is_reported_as_a_size_problem(): void
    {
        Http::fake(['api.groq.com/*' => Http::response([
            'error' => ['code' => 'rate_limit_exceeded', 'message' => 'Request too large'],
        ], 413)]);

        try {
            $this->groq()->generate('Very long prompt');
            $this->fail('Expected AiUnavailableException');
        } catch (AiUnavailableException $e) {
            $this->assertStringContainsString('too large', strtolower($e->getMessage()));
        }
    }

    public function test_a_429_is_reported_as_busy(): void
    {
        Http::fake(['api.groq.com/*' => Http::response(['error' => 'slow down'], 429)]);

        $this->expectException(AiUnavailableException::class);
        $this->expectExceptionMessageMatches('/busy/i');

        $this->groq()->generate('Hi');
    }

    public function test_a_rejected_key_is_reported_clearly(): void
    {
        Http::fake(['api.groq.com/*' => Http::response(['error' => 'invalid api key'], 401)]);

        try {
            $this->groq()->generate('Hi');
            $this->fail('Expected AiUnavailableException');
        } catch (AiUnavailableException $e) {
            $this->assertStringContainsString('key', strtolower($e->getMessage()));
        }
    }

    /**
     * Reasoning models spend output tokens before emitting content, so an
     * empty body means the budget ran out rather than a refusal.
     */
    public function test_an_empty_completion_is_reported_actionably(): void
    {
        Http::fake(['api.groq.com/*' => Http::response($this->groqReply(''), 200)]);

        try {
            $this->groq()->generate('Hi');
            $this->fail('Expected AiUnavailableException');
        } catch (AiUnavailableException $e) {
            $this->assertStringContainsString('empty', strtolower($e->getMessage()));
        }
    }

    public function test_the_gemini_driver_uses_the_google_endpoint(): void
    {
        config([
            'services.ai.provider' => 'gemini',
            'services.gemini.key' => 'g-key',
            'services.gemini.model' => 'gemini-2.0-flash',
        ]);

        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'Gemini answer.']]]]],
        ], 200)]);

        $this->assertSame('Gemini answer.', (new AiService)->generate('Question?'));

        Http::assertSent(fn ($request) => $request->hasHeader('x-goog-api-key', 'g-key'));
    }

    public function test_no_key_means_not_configured_and_no_request_is_made(): void
    {
        config(['services.ai.provider' => 'groq', 'services.groq.key' => null]);
        Http::fake();

        $service = new AiService;
        $this->assertFalse($service->isConfigured());

        $this->expectException(AiUnavailableException::class);

        try {
            $service->generate('Hi');
        } finally {
            Http::assertNothingSent();
        }
    }
}
