<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submission_pre_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained('submissions')->onDelete('cascade');
            $table->foreignId('step_content_id')->constrained('submission_step_contents')->onDelete('cascade');
            $table->foreignId('staff_id')->constrained('users');
            $table->string('action', 30);
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->index(['submission_id', 'step_content_id']);
            $table->index(['staff_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_pre_approvals');
    }
};
