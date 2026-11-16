<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Who asked for what and what it cost. No prompt, no answer.
        Schema::create('ai_usage', function (Blueprint $table): void {
            $table->id();
            $table->string('task', 32);
            $table->string('module', 100)->nullable();
            $table->unsignedBigInteger('admin_user_id')->nullable()->index();
            $table->string('provider', 64)->nullable();
            $table->string('model', 191)->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('duration_ms')->default(0);
            $table->boolean('succeeded')->default(true);
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage');
    }
};
