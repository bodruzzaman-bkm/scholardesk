<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Gives every pre-existing collection an explicit OWNER membership row.
 *
 * Without this, collections created before sharing existed would have no
 * members, and any membership-driven query (shared-with-me lists, the members
 * panel) would show the owner as absent from their own collection.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $rows = DB::table('collections')
            ->select('id', 'user_id')
            ->get()
            ->map(fn ($c) => [
                'collection_id' => $c->id,
                'user_id' => $c->user_id,
                'role' => 'owner',
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all();

        foreach (array_chunk($rows, 100) as $chunk) {
            // insertOrIgnore so re-running after a partial failure is safe.
            DB::table('collection_members')->insertOrIgnore($chunk);
        }
    }

    public function down(): void
    {
        DB::table('collection_members')->where('role', 'owner')->delete();
    }
};
