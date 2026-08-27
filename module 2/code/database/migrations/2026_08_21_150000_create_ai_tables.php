<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The retrieval + AI layer: extracted text, chunks with embeddings, chat
 * sessions and saved literature reviews.
 *
 * ScholarDesk stores embeddings in a pgvector column and searches with the
 * `<=>` operator. This project runs on SQLite, which has no vector type, so
 * embeddings are stored as a JSON array of floats and cosine similarity is
 * computed in PHP (see EmbeddingService). That is slower asymptotically but
 * exact rather than approximate, and perfectly adequate for a personal
 * library — see the note in VectorSearchService.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('papers', function (Blueprint $table) {
            // Extracted PDF text, used for chunking, summaries and RAG.
            $table->longText('full_text')->nullable()->after('abstract');
            // Null = never attempted, set = when indexing last completed.
            $table->timestamp('indexed_at')->nullable()->after('full_text');
            $table->string('index_status')->nullable()->after('indexed_at');
        });

        Schema::create('paper_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paper_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('chunk_index');
            $table->text('content');
            // JSON array of floats; null until the embedding is computed.
            $table->json('embedding')->nullable();
            $table->timestamps();

            $table->unique(['paper_id', 'chunk_index']);
            $table->index('paper_id');
        });

        Schema::create('chat_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('scope'); // paper | collection
            $table->foreignId('paper_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('collection_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'scope']);
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_session_id')->constrained()->cascadeOnDelete();
            $table->string('role'); // user | assistant
            $table->text('content');
            // [{paper_id, title, snippet, chunk_index}]
            $table->json('citations')->nullable();
            $table->timestamps();

            $table->index('chat_session_id');
        });

        Schema::create('literature_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->longText('content');
            $table->json('paper_ids');
            $table->timestamps();

            $table->index(['collection_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('literature_reviews');
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_sessions');
        Schema::dropIfExists('paper_chunks');

        Schema::table('papers', function (Blueprint $table) {
            $table->dropColumn(['full_text', 'indexed_at', 'index_status']);
        });
    }
};
