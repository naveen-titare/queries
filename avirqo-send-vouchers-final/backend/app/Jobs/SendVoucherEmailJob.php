<?php

namespace App\Jobs;

use App\Services\SendVouchers\SendVoucherService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendVoucherEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [60, 300, 900]; // 1min, 5min, 15min
    public $maxExceptions = 3;
    public $timeout = 300; // 5 minutes for large Excel

    public function __construct(public int $orderId) {}

    public function handle(SendVoucherService $service): void
    {
        Log::info("SendVoucherEmailJob started for order ID {$this->orderId}, attempt {$this->attempts()}");
        
        try {
            $service->sendOrderEmail($this->orderId);
        } catch (\Exception $e) {
            Log::error("SendVoucherEmailJob failed for order {$this->orderId}: " . $e->getMessage(), [
                'order_id' => $this->orderId,
                'attempt' => $this->attempts(),
            ]);

            // If final attempt, mark as failed and restore
            if ($this->attempts() >= $this->tries) {
                $service->markOrderFailed($this->orderId, "Job failed after {$this->tries} attempts: " . $e->getMessage());
            }

            throw $e; // Re-throw to trigger retry/backoff
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::critical("SendVoucherEmailJob permanently failed for order {$this->orderId}", [
            'exception' => $exception->getMessage(),
        ]);
    }
}
