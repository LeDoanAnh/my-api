<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submission_categories', function (Blueprint $table) {
            if (!Schema::hasColumn('submission_categories', 'apply_for_dept_id')) {
                $table->foreignId('apply_for_dept_id')
                    ->nullable()
                    ->after('description')
                    ->constrained('departments')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('submission_categories', 'status')) {
                $table->string('status')->default('active')->after('apply_for_dept_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('submission_categories', function (Blueprint $table) {
            if (Schema::hasColumn('submission_categories', 'status')) {
                $table->dropColumn('status');
            }

            if (Schema::hasColumn('submission_categories', 'apply_for_dept_id')) {
                $table->dropConstrainedForeignId('apply_for_dept_id');
            }
        });
    }
};
