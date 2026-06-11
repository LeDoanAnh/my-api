<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approval_log', function (Blueprint $table) {
            if (!Schema::hasColumn('approval_log', 'step_content_id')) {
                $table->foreignId('step_content_id')
                    ->nullable()
                    ->after('submission_id')
                    ->constrained('submission_step_contents')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('approval_log', function (Blueprint $table) {
            if (Schema::hasColumn('approval_log', 'step_content_id')) {
                $table->dropConstrainedForeignId('step_content_id');
            }
        });
    }
};
