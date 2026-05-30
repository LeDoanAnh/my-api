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
        Schema::create('approval_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('submission_categories');
            $table->integer('step_order'); // Bước số mấy
            $table->foreignId('target_role_id')->constrained('roles'); // Ai duyệt bước này
            $table->foreignId('target_dept_id')->nullable()->constrained('departments');
            $table->foreignId('apply_for_dept_id')->nullable()->constrained('departments');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approval_configs');
    }
};
