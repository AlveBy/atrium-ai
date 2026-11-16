<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_articles', function (Blueprint $table): void {
            $table->id();
            $table->json('name');
            $table->string('code')->nullable();
            $table->json('preview_text')->nullable();
            $table->text('detail_text')->nullable();
            $table->string('picture')->nullable();
            $table->string('picture_alt')->nullable();
            $table->string('meta_title')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_articles');
    }
};
