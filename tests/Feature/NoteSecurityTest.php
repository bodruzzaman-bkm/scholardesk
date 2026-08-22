<?php

namespace Tests\Feature;

use App\Models\Note;
use App\Models\Paper;
use App\Models\User;
use App\Support\Markdown;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Notes are user-written markdown rendered as raw HTML with {!! !!}, so they
 * are the app's most direct stored-XSS surface.
 */
class NoteSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_raw_html_in_a_note_is_stripped(): void
    {
        $html = Markdown::render('Hello <script>alert(1)</script> world');

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringContainsString('Hello', $html);
    }

    public function test_img_onerror_payloads_are_stripped(): void
    {
        $html = Markdown::render('<img src=x onerror="alert(1)">');

        $this->assertStringNotContainsString('onerror', $html);
    }

    /**
     * CommonMark permits `javascript:` hrefs by default, so a plain markdown
     * link is an XSS vector unless unsafe links are disabled.
     */
    public function test_javascript_links_are_neutralised(): void
    {
        $html = Markdown::render('[click me](javascript:alert(1))');

        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringContainsString('click me', $html);
    }

    public function test_data_uri_links_are_neutralised(): void
    {
        $html = Markdown::render('[x](data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==)');

        $this->assertStringNotContainsString('data:text/html', $html);
    }

    public function test_ordinary_markdown_still_renders(): void
    {
        $html = Markdown::render("## Heading\n\n- one\n- two\n\n**bold** and [link](https://example.com)");

        $this->assertStringContainsString('<h2>', $html);
        $this->assertStringContainsString('<li>', $html);
        $this->assertStringContainsString('<strong>', $html);
        $this->assertStringContainsString('href="https://example.com"', $html);
    }

    public function test_a_stored_script_note_is_not_rendered_on_the_paper_page(): void
    {
        $user = User::factory()->create();
        $paper = Paper::create(['title' => 'Victim', 'user_id' => $user->id]);

        Note::create([
            'paper_id' => $paper->id,
            'user_id' => $user->id,
            'content' => 'Innocent text <script>alert("pwned")</script>',
        ]);

        $this->actingAs($user)->get("/papers/{$paper->id}")
            ->assertOk()
            ->assertSee('Innocent text')
            ->assertDontSee('<script>alert("pwned")</script>', false);
    }

    public function test_blank_markdown_renders_as_an_empty_string(): void
    {
        $this->assertSame('', Markdown::render(null));
        $this->assertSame('', Markdown::render(''));
    }
}
