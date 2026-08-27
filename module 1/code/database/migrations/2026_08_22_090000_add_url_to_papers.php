<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Papers can be added by pasting an article URL, not only a DOI
 * (requirement 2). The canonical link is kept so the paper detail page can
 * offer "view at publisher" even when no DOI was ever resolved.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('papers', function (Blueprint $table) {
            $table->string('url', 2048)->nullable()->after('doi');
            // upload | doi | url - how this paper entered the library.
            $table->string('source', 20)->default('upload')->after('url');
        });
    }

    public function down(): void
    {
        Schema::table('papers', function (Blueprint $table) {
            $table->dropColumn(['url', 'source']);
        });
    }
};
