<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user control over the email copy of in-app notifications.
 *
 * Stored as a {type => bool} map keyed by App\Enums\NotificationType values.
 * NULL deliberately means "email everything" — that is exactly how the app
 * behaved before this column existed, so every pre-existing row keeps its
 * current behaviour without a backfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('email_prefs')->nullable()->after('locale');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('email_prefs');
        });
    }
};
