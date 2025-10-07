<?php

namespace App\Jobs;

use App\Models\ComparisonRecord;
use App\Services\GHLComparisonService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessGHLComparisonJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $comparison;

    /**
     * Create a new job instance.
     */
    public function __construct(ComparisonRecord $comparison)
    {
        $this->comparison = $comparison;
    }

    /**
     * Execute the job.
     */
    public function handle(GHLComparisonService $ghlComparisonService): void
    {
        Log::info("Starting GHL comparison job for comparison #{$this->comparison->id}");
        
        try {
            $ghlComparisonService->processComparison($this->comparison);
            Log::info("GHL comparison job completed successfully for comparison #{$this->comparison->id}");
        } catch (\Exception $e) {
            Log::error("GHL comparison job failed for comparison #{$this->comparison->id}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("GHL comparison job failed permanently for comparison #{$this->comparison->id}: " . $exception->getMessage());
        
        $this->comparison->update([
            'status' => 'failed',
            'error_message' => $exception->getMessage(),
        ]);
    }
}