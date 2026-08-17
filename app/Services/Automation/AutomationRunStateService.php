<?php

namespace App\Services\Automation;

use App\Models\AutomationRun;
use InvalidArgumentException;

class AutomationRunStateService
{
    protected array $validStatusTransitions = [
        'queued' => ['running', 'failed', 'skipped'],
        'running' => ['completed', 'failed', 'skipped'],
        'completed' => [],
        'failed' => ['running'], // For retry
        'skipped' => [],
    ];

    public function markRunning(AutomationRun $run): void
    {
        $this->transitionStatus($run, 'running');
        $run->update([
            'started_at' => $run->started_at ?? now(),
            'attempts' => $run->attempts + 1,
            'current_stage' => 'initialization',
        ]);
    }

    public function moveToStage(AutomationRun $run, string $stage): void
    {
        $run->update(['current_stage' => $stage]);
    }

    public function markCompleted(AutomationRun $run): void
    {
        $this->transitionStatus($run, 'completed');
        $run->update([
            'current_stage' => 'completed',
            'completed_at' => now(),
        ]);
    }

    public function markFailed(AutomationRun $run, \Throwable $exception): void
    {
        // Sanitize error string for db to avoid dumping everything
        $errorString = $exception->getMessage() . ' in ' . basename($exception->getFile()) . ':' . $exception->getLine();

        $this->transitionStatus($run, 'failed');
        $run->update([
            'current_stage' => 'failed',
            'failed_at' => now(),
            'last_error' => $errorString,
        ]);
    }

    public function markSkipped(AutomationRun $run, string $reason): void
    {
        $this->transitionStatus($run, 'skipped');
        $run->update([
            'current_stage' => 'completed',
            'last_error' => $reason,
        ]);
    }

    protected function transitionStatus(AutomationRun $run, string $newStatus): void
    {
        $currentStatus = $run->status;

        // Allow idempotency
        if ($currentStatus === $newStatus) {
            return;
        }

        if (!in_array($newStatus, $this->validStatusTransitions[$currentStatus] ?? [])) {
            throw new InvalidArgumentException("Invalid status transition from {$currentStatus} to {$newStatus}");
        }

        $run->update(['status' => $newStatus]);
    }
}
