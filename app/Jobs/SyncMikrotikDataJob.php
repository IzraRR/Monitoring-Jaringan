<?php

namespace App\Jobs;

use App\Services\MikrotikService;
use App\Services\CacheService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncMikrotikDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 180;
    public $backoff = [15, 45, 90];

    protected $syncType;

    /**
     * Create a new job instance.
     *
     * @param string $syncType (summary, realtime, users, all)
     */
    public function __construct(string $syncType = 'summary')
    {
        $this->syncType = $syncType;
    }

    /**
     * Execute the job.
     */
    public function handle(MikrotikService $mikrotikService, CacheService $cacheService): void
    {
        try {
            Log::info('Starting MikroTik data sync', [
                'sync_type' => $this->syncType,
                'attempt' => $this->attempts()
            ]);

            $success = false;

            switch ($this->syncType) {
                case 'summary':
                    $result = $mikrotikService->getSystemSummary();
                    $success = $result['connected'] ?? false;
                    break;

                case 'realtime':
                    $result = $mikrotikService->getRealtimeStats();
                    $success = $result['connected'] ?? false;
                    break;

                case 'users':
                    // Sync all pelanggan to MikroTik
                    $result = $mikrotikService->syncAllPelanggan();
                    $success = $result['success'] ?? false;
                    break;

                case 'all':
                    // Sync everything
                    $summary = $mikrotikService->getSystemSummary();
                    $realtime = $mikrotikService->getRealtimeStats();
                    $success = ($summary['connected'] ?? false) && ($realtime['connected'] ?? false);
                    break;

                default:
                    Log::warning('Invalid sync type', ['sync_type' => $this->syncType]);
                    return;
            }

            if ($success) {
                Log::info('MikroTik data sync completed', [
                    'sync_type' => $this->syncType
                ]);

                // Invalidate relevant cache
                $cacheService->invalidateMikrotik();
                
                if (in_array($this->syncType, ['users', 'all'])) {
                    $cacheService->invalidatePelanggan();
                }
            } else {
                Log::warning('MikroTik data sync failed', [
                    'sync_type' => $this->syncType
                ]);

                // Retry
                $this->release(45);
            }
        } catch (\Exception $e) {
            Log::error('MikroTik data sync job exception', [
                'sync_type' => $this->syncType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            if ($this->attempts() < $this->tries) {
                $this->release(90);
            } else {
                $this->fail($e);
            }
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('MikroTik data sync job failed permanently', [
            'sync_type' => $this->syncType,
            'error' => $exception->getMessage()
        ]);
    }
}
