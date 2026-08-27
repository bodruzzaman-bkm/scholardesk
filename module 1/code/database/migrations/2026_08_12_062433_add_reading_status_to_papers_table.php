<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('papers', function (Blueprint $table) {
            // Adding reading_status column with enum type and default value
            $table->enum('reading_status', ['to read', 'reading', 'read'])->default('to read')->after('file_path');
        });
    }

    public function down(): void
    {
        Schema::table('papers', function (Blueprint $table) {
            $table->dropColumn('reading_status');
        });
    }
};