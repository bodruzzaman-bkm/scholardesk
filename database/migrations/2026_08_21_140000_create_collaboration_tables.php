<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Collaboration: shared collections, threaded comments and an activity feed.
 *
 * Mirrors ScholarDesk's CollectionMember / Comment / Activity models, adapted
 * to Laravel conventions (snake_case, integer FKs, timestamps).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Per-collection membership with a role. The collection's creator is
        // also stored denormalised on collections.user_id, and is seeded here
        // as an OWNER row so every access check reads from one place.
        Schema::create('collection_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('viewer'); // viewer | editor | owner
            $table->timestamps();

            $table->unique(['collection_id', 'user_id']);
            $table->index('user_id');
        });

        // Threaded discussion, scoped to a collection and optionally to one
        // paper within it.
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('paper_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('comments')->cascadeOnDelete();
            $table->text('content');
            // Moderation: admins hide rather than destroy, so the thread keeps
            // its shape and the action is reversible.
            $table->boolean('is_hidden')->default(false);
            $table->timestamps();

            $table->index(['collection_id', 'paper_id']);
            $table->index('parent_id');
        });

        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['collection_id', 'created_at']);
        });

        Schema::create('notifications_inapp', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('message');
            $table->string('link')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'is_read']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications_inapp');
        Schema::dropIfExists('activities');
        Schema::dropIfExists('comments');
        Schema::dropIfExists('collection_members');
    }
};
