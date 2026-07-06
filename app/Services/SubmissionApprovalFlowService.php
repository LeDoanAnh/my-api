<?php

namespace App\Services;

use App\Http\Controllers\Api\NotificationController;
use App\Models\Submission;
use App\Models\SubmissionStepContent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SubmissionApprovalFlowService
{
    public function __construct(
        private readonly NotificationController $notificationController
    ) {
    }

    public function dispatchSubmission(Submission $submission): void
    {
        $submission->loadMissing('creator');

        if (in_array($submission->status, ['approved', 'rejected'], true)) {
            $this->notifyCreatorIfAvailable($submission);
            return;
        }

        $currentStep = $this->resolveCurrentStep($submission->id);
        if ($currentStep) {
            $this->notificationController->notifyPreApproversForStep($currentStep);
        }
    }

    public function dispatchSubmissionById(int $submissionId): void
    {
        $submission = Submission::with('creator')->find($submissionId);

        if (!$submission) {
            return;
        }

        $this->dispatchSubmission($submission);
    }

    public function notifyCurrentStepApprovers(SubmissionStepContent $step): void
    {
        $this->notificationController->notifyApproversForStep($step);
    }

    public function notifyRevisionRequested(Submission $submission, User $staff, ?string $comment = null): void
    {
        $submission->loadMissing('creator');

        if (!$submission->creator) {
            return;
        }

        $this->notificationController->notifyCreatorRevisionRequested(
            $submission->creator,
            $submission,
            $staff,
            $comment
        );
    }

    private function notifyCreatorIfAvailable(Submission $submission): void
    {
        if (!$submission->creator) {
            return;
        }

        $this->notificationController->notifyCreator(
            $submission->creator,
            $submission,
            $submission->status
        );
    }

    private function resolveCurrentStep(int $submissionId): ?SubmissionStepContent
    {
        $stepOrderColumn = $this->resolveStepOrderColumn();

        return SubmissionStepContent::where('submission_id', $submissionId)
            ->orderBy($stepOrderColumn)
            ->orderBy('id')
            ->first();
    }

    private function resolveStepOrderColumn(): string
    {
        $columns = DB::select("SHOW COLUMNS FROM submission_step_contents LIKE 'step_order%'");

        if (!empty($columns)) {
            return $columns[0]->Field;
        }

        return 'step_oder';
    }
}
