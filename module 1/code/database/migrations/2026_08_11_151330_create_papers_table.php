<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('papers', function (Blueprint $table) {
            $table->id();
            
            // Foreign Key: User who uploaded the paper
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); 
            
            // Paper details
            $table->string('title'); 
            $table->text('abstract')->nullable(); 
            $table->string('doi')->unique()->nullable(); 
            $table->string('file_path')->nullable(); 
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('papers');
    }
};
