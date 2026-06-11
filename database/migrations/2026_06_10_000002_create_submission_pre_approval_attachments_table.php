<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submission_pre_approval_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pre_approval_id')
                ->constrained('submission_pre_approvals')
                ->onDelete('cascade');
            $table->string('file_path');
            $table->string('file_name');
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('file_type', 20)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_pre_approval_attachments');
    }
};
