<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boxes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('size');
            $table->timestamps();
        });

        Schema::create('box_images', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('box_id');
            $table->string('url');
            $table->string('alt_text')->nullable();
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->timestamps();
            
            $table->foreign('box_id')->references('id')->on('boxes')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('box_images');
        Schema::dropIfExists('boxes');
    }
};