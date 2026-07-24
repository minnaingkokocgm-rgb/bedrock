<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submission_ai_advices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->longText('extracted_content')->nullable();
            $table->string('extraction_status');
            $table->text('extraction_error')->nullable();
            $table->text('system_prompt_snapshot');
            $table->text('type_rules_snapshot');
            $table->string('model_id');
            $table->string('ai_verdict')->nullable();
            $table->text('ai_reason')->nullable();
            $table->longText('ai_raw_response')->nullable();
            $table->string('status')->default('pending')->index();
            $table->timestamp('analyzed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_ai_advices');
    }
};
