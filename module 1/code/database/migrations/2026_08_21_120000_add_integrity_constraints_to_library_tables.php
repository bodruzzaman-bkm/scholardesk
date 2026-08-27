<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the integrity constraints the original tables were missing.
 *
 * ScholarDesk's Prisma schema declares composite primary keys on both pivots
 * (@@id([collectionId, paperId]) and @@id([paperId, tagId])) plus a unique
 * (userId, name) on tags. The Laravel migrations had neither, so a paper could
 * be attached to the same collection twice and a user could create two tags
 * with the same name.
 *
 * Existing duplicates are removed before the constraints are applied so this
 * migration is safe to run against a database that already has data.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->deleteDuplicatePivotRows('collection_paper', ['collection_id', 'paper_id']);
        $this->deleteDuplicatePivotRows('paper_tag', ['paper_id', 'tag_id']);
        $this->deleteDuplicateTags();

        Schema::table('collection_paper', function (Blueprint $table) {
            $table->unique(['collection_id', 'paper_id'], 'collection_paper_unique');
        });

        Schema::table('paper_tag', function (Blueprint $table) {
            $table->unique(['paper_id', 'tag_id'], 'paper_tag_unique');
        });

        Schema::table('tags', function (Blueprint $table) {
            $table->unique(['user_id', 'name'], 'tags_user_name_unique');
        });

        Schema::table('papers', function (Blueprint $table) {
            // The library list always filters by owner, and DOI lookups happen
            // on every "add paper" that supplies one.
            $table->index('user_id', 'papers_user_id_index');
            $table->index('doi', 'papers_doi_index');
            $table->index('reading_status', 'papers_reading_status_index');
        });

        Schema::table('collections', function (Blueprint $table) {
            $table->index('user_id', 'collections_user_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('collection_paper', function (Blueprint $table) {
            $table->dropUnique('collection_paper_unique');
        });

        Schema::table('paper_tag', function (Blueprint $table) {
            $table->dropUnique('paper_tag_unique');
        });

        Schema::table('tags', function (Blueprint $table) {
            $table->dropUnique('tags_user_name_unique');
        });

        Schema::table('papers', function (Blueprint $table) {
            $table->dropIndex('papers_user_id_index');
            $table->dropIndex('papers_doi_index');
            $table->dropIndex('papers_reading_status_index');
        });

        Schema::table('collections', function (Blueprint $table) {
            $table->dropIndex('collections_user_id_index');
        });
    }

    /**
     * Keep the lowest id for each duplicated pair and delete the rest.
     *
     * @param  list<string>  $columns
     */
    private function deleteDuplicatePivotRows(string $table, array $columns): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $keepIds = DB::table($table)
            ->selectRaw('MIN(id) as id')
            ->groupBy($columns)
            ->pluck('id');

        DB::table($table)->whereNotIn('id', $keepIds)->delete();
    }

    /** Re-point papers at the surviving tag before deleting duplicate tags. */
    private function deleteDuplicateTags(): void
    {
        if (! Schema::hasTable('tags')) {
            return;
        }

        $duplicates = DB::table('tags')
            ->select('user_id', 'name', DB::raw('MIN(id) as keep_id'))
            ->groupBy('user_id', 'name')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $row) {
            $doomed = DB::table('tags')
                ->where('user_id', $row->user_id)
                ->where('name', $row->name)
                ->where('id', '!=', $row->keep_id)
                ->pluck('id');

            if ($doomed->isEmpty()) {
                continue;
            }

            // Move associations onto the surviving tag, then drop rows that
            // would collide with an existing pair.
            DB::table('paper_tag')->whereIn('tag_id', $doomed)->update(['tag_id' => $row->keep_id]);
            DB::table('tags')->whereIn('id', $doomed)->delete();
        }
    }
};
