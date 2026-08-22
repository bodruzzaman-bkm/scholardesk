<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Scopes DOI uniqueness to the owning user.
 *
 * `create_papers_table` declared `doi` as globally unique. In a multi-user
 * library that is wrong: once one researcher saved a DOI, nobody else on the
 * instance could ever add that paper. Uniqueness belongs on (user_id, doi) —
 * a user may not add the same paper twice, but two users may each hold it.
 *
 * NULL doi values stay exempt, because SQL treats NULLs as distinct in a
 * unique index, so papers added by PDF upload are unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('papers', function (Blueprint $table) {
            $table->dropUnique('papers_doi_unique');
        });

        Schema::table('papers', function (Blueprint $table) {
            $table->unique(['user_id', 'doi'], 'papers_user_doi_unique');
        });
    }

    public function down(): void
    {
        Schema::table('papers', function (Blueprint $table) {
            $table->dropUnique('papers_user_doi_unique');
        });

        Schema::table('papers', function (Blueprint $table) {
            $table->unique('doi', 'papers_doi_unique');
        });
    }
};
