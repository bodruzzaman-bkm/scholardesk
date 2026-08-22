<?php

namespace Tests\Feature;

use App\Models\Paper;
use App\Models\User;
use App\Services\MetadataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * An imported paper is only useful if it can be read, so an open-access PDF
 * advertised by the source is fetched. Without it, a link-imported paper has
 * no reader, no highlights, no summary and no semantic search.
 */
class OpenAccessPdfTest extends TestCase
{
    use RefreshDatabase;

    /** A minimal but genuine PDF: the magic bytes are what is checked. */
    private function pdfBytes(): string
    {
        return "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF";
    }

    private function arxivAtom(): string
    {
        return <<<'XML'
        <feed><entry>
          <title>Attention Is All You Need</title>
          <author><name>Ashish Vaswani</name></author>
          <published>2017-06-12T00:00:00Z</published>
          <summary>We propose a new architecture.</summary>
        </entry></feed>
        XML;
    }

    public function test_an_arxiv_import_fetches_the_open_access_pdf(): void
    {
        Storage::fake('public');

        Http::fake([
            'export.arxiv.org/*' => Http::response($this->arxivAtom(), 200),
            'arxiv.org/pdf/*' => Http::response($this->pdfBytes(), 200, ['Content-Type' => 'application/pdf']),
            'arxiv.org/*' => Http::response('', 404),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/papers', ['identifier' => 'https://arxiv.org/abs/1706.03762'])
            ->assertRedirect();

        $paper = Paper::firstOrFail();

        $this->assertNotNull($paper->file_path, 'No PDF was stored for the imported paper');
        Storage::disk('public')->assertExists($paper->file_path);
        // Which means the reader and the AI layer are available for it.
        $this->assertTrue($paper->hasPdf());
    }

    public function test_a_failed_pdf_fetch_still_leaves_the_paper_with_its_metadata(): void
    {
        Storage::fake('public');

        Http::fake([
            'export.arxiv.org/*' => Http::response($this->arxivAtom(), 200),
            'arxiv.org/*' => Http::response('', 404),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/papers', ['identifier' => 'https://arxiv.org/abs/1706.03762'])
            ->assertRedirect();

        $paper = Paper::firstOrFail();

        $this->assertNull($paper->file_path);
        // The import still succeeded; only the file is missing.
        $this->assertSame('Attention Is All You Need', $paper->title);
    }

    /** A response that is not really a PDF must not be stored as one. */
    public function test_a_non_pdf_response_is_refused(): void
    {
        $service = app(MetadataService::class);

        Http::fake(['example.org/*' => Http::response('<html>not a pdf</html>', 200, ['Content-Type' => 'application/pdf'])]);

        $this->assertNull($service->downloadPdf('https://example.org/fake.pdf'));
    }

    public function test_an_oversized_pdf_is_refused(): void
    {
        $service = app(MetadataService::class);

        $big = '%PDF-1.4'.str_repeat('a', 5000);
        Http::fake(['example.org/*' => Http::response($big, 200, ['Content-Type' => 'application/pdf'])]);

        $this->assertNull($service->downloadPdf('https://example.org/big.pdf', maxBytes: 1000));
    }

    public function test_plain_http_is_refused(): void
    {
        $this->assertNull(app(MetadataService::class)->downloadPdf('http://example.org/paper.pdf'));
    }

    /**
     * The server fetches a user-supplied URL, so it must not be usable to
     * probe the machine or the local network.
     */
    public function test_loopback_and_private_hosts_are_refused(): void
    {
        $service = app(MetadataService::class);

        foreach ([
            'https://127.0.0.1/secret.pdf',
            'https://localhost/secret.pdf',
            'https://10.0.0.5/secret.pdf',
            'https://192.168.1.1/secret.pdf',
            'https://169.254.169.254/latest/meta-data',
        ] as $url) {
            $this->assertNull($service->downloadPdf($url), "should have refused: {$url}");
        }

        // And nothing was even attempted.
        Http::assertNothingSent();
    }

    public function test_a_blank_url_is_refused(): void
    {
        $this->assertNull(app(MetadataService::class)->downloadPdf(null));
        $this->assertNull(app(MetadataService::class)->downloadPdf(''));
    }
}
