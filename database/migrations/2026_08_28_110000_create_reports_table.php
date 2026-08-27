<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Content reports (requirement 22: administrators "moderate reported
 * content").
 *
 * Administrators could already hide a comment, but nothing let a user flag
 * one, so there was no queue to moderate — the requirement was half built.
 *
 * The target is polymorphic because "content" is not only comments: an
 * uploaded paper can be inappropriate too, and a single queue is what an
 * administrator actually wants to work through.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();

            // The reporter. Cascades: if the account goes, so does the report.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Comment or Paper. Not constrained — a morph cannot carry a
            // foreign key — so deletion is handled in the models instead.
            $table->morphs('reportable');

            $table->string('reason', 500);
            $table->string('status')->default('open');

            // Who closed it, and when. Nulled rather than cascaded on user
            // delete: losing the moderator's account must not silently reopen
            // a settled report.
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();

            // The queue is always read status-first, newest first.
            $table->index(['status', 'created_at']);

            // One open report per person per item. Without this the queue is
            // floodable by a single user clicking Report repeatedly, which
            // would bury the genuine reports underneath.
            $table->unique(['user_id', 'reportable_type', 'reportable_id'], 'reports_one_per_user_per_item');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
