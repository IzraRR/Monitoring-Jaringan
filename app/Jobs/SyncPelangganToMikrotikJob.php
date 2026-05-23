<?php

namespace App\Jobs;

use App\Models\Pelanggan;
use App\Services\MikrotikService;
use App\Services\CacheService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncPelangganToMikrotikJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 120;
    public $backoff = [10, 30, 60];

    protected $pelangganId;
    protected $action;

    /**
     * Create a new job instance.
     *
     * @param int $pelangganId
     * @param string $action (create, update, delete, lock, unlock)
     */
    public function __construct(int $pelangganId, string $action = 'update')
    {
        $this->pelangganId = $pelangganId;
        $this->action = $action;
    }

    /**
     * Execute the job.
     */
    public function handle(MikrotikService $mikrotikService, CacheService $cacheService): void
    {
        try {
            $pelanggan = Pelanggan::with('paket')->find($this->pelangganId);

            if (!$pelanggan) {
                Log::warning('MikroTik sync job: pelanggan not found, skipping', [
                    'pelanggan_id' => $this->pelangganId,
                    'action' => $this->action,
                ]);
                return;
            }

            Log::info('Starting MikroTik sync job', [
                'pelanggan_id' => $this->pelangganId,
                'action' => $this->action,
                'attempt' => $this->attempts()
            ]);

            $result = match ($this->action) {
                'create' => $mikrotikService->syncPelangganCreated($pelanggan),
                'update' => $mikrotikService->syncPelangganUpdated($pelanggan, $pelanggan->username_mikrotik),
                'delete' => $mikrotikService->syncPelangganDeleted($pelanggan->username_mikrotik),
                'lock' => $mikrotikService->setPelangganState($pelanggan, false),
                'unlock' => $mikrotikService->setPelangganState($pelanggan, true),
                default => ['success' => false, 'message' => 'Invalid action']
            };

            if ($result['success']) {
                Log::info('MikroTik sync completed successfully', [
                    'pelanggan_id' => $this->pelangganId,
                    'action' => $this->action
                ]);

                // Invalidate cache after successful sync
                $cacheService->invalidatePelanggan($this->pelangganId);
                $cacheService->invalidateMikrotik();
            } else {
                Log::warning('MikroTik sync failed', [
                    'pelanggan_id' => $this->pelangganId,
                    'action' => $this->action,
                    'message' => $result['message']
                ]);

                // Retry the job
                $this->release(30);
            }
        } catch (\Exception $e) {
            Log::error('MikroTik sync job exception', [
                'pelanggan_id' => $this->pelangganId,
                'action' => $this->action,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Retry or fail
            if ($this->attempts() < $this->tries) {
                $this->release(60);
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
        Log::error('MikroTik sync job failed permanently', [
            'pelanggan_id' => $this->pelangganId,
            'action' => $this->action,
            'error' => $exception->getMessage()
        ]);
    }
}
