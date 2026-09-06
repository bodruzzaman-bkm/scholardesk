<?php

namespace App\Services;

use App\Models\Paper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Turns an uploaded PDF into retrievable chunks: extract text, chunk it,
 * embed each chunk, store the vectors.
 *
 * Runs from IndexPaper (a queued job) so that adding a paper returns
 * immediately. Failure is always recorded on the paper rather than thrown, so
 * a bad PDF degrades that one paper's AI features and nothing else.
 */
class IndexingService
{
    public function __construct(
        private PdfTextService $pdf,
        private ChunkService $chunker,
        private EmbeddingService $embeddings,
    ) {}

    public function index(Paper $paper): string
    {
        if (! $paper->hasPdf()) {
            $this->markStatus($paper, 'no_file');

            return 'no_file';
        }

        $result = $this->pdf->extract($paper->file_path);

        if ($result['status'] !== 'indexed' || blank($result['text'])) {
            // 'no_text' means a scanned PDF; 'error' means a malformed one.
            // Both leave the paper fully usable, minus retrieval.
            $this->markStatus($paper, $result['status']);

            return $result['status'];
        }

        return $this->storeText($paper, $result['text']);
    }

    /**
     * Chunk, embed and store text that is already in hand.
     *
     * Split out of index() because retrieval does not actually care where the
     * text came from — only that it exists. index() gets it from a PDF; the
     * demo seeder has it already and has no file to parse. Both need the same
     * chunk-embed-store transaction, and it should exist once.
     */
    public function storeText(Paper $paper, string $text): string
    {
        $chunks = $this->chunker->chunk($text);

        if ($chunks === []) {
            $this->markStatus($paper, 'no_text');

            return 'no_text';
        }

        try {
            DB::transaction(function () use ($paper, $text, $chunks) {
                // Re-indexing replaces the previous chunks wholesale rather
                // than trying to reconcile them.
                $paper->chunks()->delete();

                $now = now();
                $rows = [];

                foreach ($chunks as $chunk) {
                    $rows[] = [
                        'paper_id' => $paper->id,
                        'chunk_index' => $chunk['index'],
                        'content' => $chunk['content'],
                        'embedding' => json_encode($this->embeddings->embed($chunk['content'])),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                // Chunked insert keeps the SQLite variable limit out of reach.
                foreach (array_chunk($rows, 50) as $batch) {
                    DB::table('paper_chunks')->insert($batch);
                }

                $paper->forceFill([
                    'full_text' => $text,
                    'indexed_at' => $now,
                    'index_status' => 'indexed',
                ])->save();
            });
        } catch (\Throwable $e) {
            Log::error('Paper indexing failed', ['paper_id' => $paper->id, 'error' => $e->getMessage()]);
            $this->markStatus($paper, 'error');

            return 'error';
        }

        return 'indexed';
    }

    private function markStatus(Paper $paper, string $status): void
    {
        $paper->forceFill([
            'index_status' => $status,
            'indexed_at' => now(),
        ])->save();
    }
}
