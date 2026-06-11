<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            UPDATE approval_log al
            JOIN (
                SELECT
                    al_inner.id AS approval_log_id,
                    MIN(sc.id) AS step_content_id,
                    COUNT(sc.id) AS matching_steps
                FROM approval_log al_inner
                JOIN users u ON u.id = al_inner.approver_id
                JOIN submission_step_contents sc
                    ON sc.submission_id = al_inner.submission_id
                    AND sc.target_dept_id = u.department_id
                WHERE al_inner.step_content_id IS NULL
                GROUP BY al_inner.id
                HAVING matching_steps = 1
            ) matched ON matched.approval_log_id = al.id
            SET al.step_content_id = matched.step_content_id
            WHERE al.step_content_id IS NULL
        ");
    }

    public function down(): void
    {
        // Data backfill is intentionally not reversed.
    }
};
