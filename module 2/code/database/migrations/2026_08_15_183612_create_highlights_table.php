<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('highlights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paper_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->text('text')->nullable(); // The actual highlighted text
            $table->text('note')->nullable(); // Margin note added by the user
            $table->string('color')->default('#FFEB3B'); // Default color (Yellow)
            $table->json('position')->nullable(); // Coordinates & page number for the PDF
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('highlights');
    }
};