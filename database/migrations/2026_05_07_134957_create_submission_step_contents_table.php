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
        Schema::create('submission_step_contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained();
            $table->integer('step_order');
            $table->foreignId('target_dept_id')->constrained('departments');
            $table->text('content_text');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('submission_step_contents');
    }
};
