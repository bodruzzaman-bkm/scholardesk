<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Account suspension (requirement 22: administrators "manage user accounts
 * and roles").
 *
 * Role management alone left an administrator with no lever over a bad
 * account: the report queue could surface an abusive user, and the only
 * available response was hiding their comments one at a time.
 *
 * Suspension rather than deletion, deliberately. Deleting a user cascades
 * through their papers, collections, notes, highlights and comments — it
 * destroys a library to silence an account, and there is no undo. A
 * suspended user keeps everything and simply cannot sign in, which is
 * reversible and proportionate.
 *
 * A nullable timestamp rather than a boolean, because "when" is worth
 * knowing when reviewing a moderation decision later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('suspended_at')->nullable()->after('role');

            // Who did it, so a decision can be traced back. Nulled rather
            // than cascaded: losing the moderator's account must not quietly
            // reinstate everyone they suspended.
            $table->foreignId('suspended_by')->nullable()->after('suspended_at')
                ->constrained('users')->nullOnDelete();

            $table->string('suspension_reason', 500)->nullable()->after('suspended_by');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('suspended_by');
            $table->dropColumn(['suspended_at', 'suspension_reason']);
        });
    }
};
