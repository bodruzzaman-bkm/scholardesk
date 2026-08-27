<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Requirement 18 is "threaded comments on shared collections AND individual
 * papers". The comments table was built for the first half only:
 * `collection_id` was NOT NULL, so a paper that belongs to no collection
 * could not be discussed at all.
 *
 * Making the column nullable is what lets a comment hang off a paper alone.
 * A comment now has exactly one anchor:
 *
 *   collection_id set, paper_id null   -> a collection thread
 *   paper_id set, collection_id null   -> a paper thread
 *   both set                           -> a paper thread inside a collection
 *                                         (the pre-existing case, unchanged)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->foreignId('collection_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Rows with no collection cannot survive a NOT NULL column, and there
        // is no sensible collection to invent for them, so they are removed
        // first. Without this the ALTER fails on any paper-only comment.
        DB::table('comments')->whereNull('collection_id')->delete();

        Schema::table('comments', function (Blueprint $table) {
            $table->foreignId('collection_id')->nullable(false)->change();
        });
    }
};
