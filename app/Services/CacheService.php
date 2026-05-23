<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CacheService
{
    /**
     * Cache duration constants (in seconds)
     */
    const CACHE_SHORT = 300;      // 5 minutes
    const CACHE_MEDIUM = 1800;    // 30 minutes
    const CACHE_LONG = 3600;      // 1 hour
    const CACHE_VERY_LONG = 86400; // 24 hours

    /**
     * Cache key prefixes
     */
    const PREFIX_PAKET = 'paket_bandwidth';
    const PREFIX_PELANGGAN = 'pelanggan';
    const PREFIX_MIKROTIK = 'mikrotik';
    const PREFIX_DASHBOARD = 'dashboard';
    const PREFIX_STATS = 'stats';

    /**
     * Remember data with caching
     *
     * @param string $key
     * @param int $ttl Time to live in seconds
     * @param callable $callback
     * @return mixed
     */
    public function remember(string $key, int $ttl, callable $callback)
    {
        try {
            $this->registerKey($key);
            return Cache::remember($key, $ttl, $callback);
        } catch (\Exception $e) {
            Log::warning('Cache remember failed', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            // If cache fails, execute callback directly
            return $callback();
        }
    }

    /**
     * Get cached data
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        try {
            return Cache::get($key, $default);
        } catch (\Exception $e) {
            Log::warning('Cache get failed', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return $default;
        }
    }

    /**
     * Store data in cache
     *
     * @param string $key
     * @param mixed $value
     * @param int $ttl
     * @return bool
     */
    public function put(string $key, $value, int $ttl): bool
    {
        try {
            $this->registerKey($key);
            return Cache::put($key, $value, $ttl);
        } catch (\Exception $e) {
            Log::warning('Cache put failed', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Forget cached data
     *
     * @param string $key
     * @return bool
     */
    public function forget(string $key): bool
    {
        try {
            return Cache::forget($key);
        } catch (\Exception $e) {
            Log::warning('Cache forget failed', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Flush cache by prefix
     *
     * @param string $prefix
     * @return void
     */
    public function flushByPrefix(string $prefix): void
    {
        try {
            $trackerKey = 'cache_keys_' . $prefix;
            $keys = Cache::get($trackerKey, []);
            foreach ($keys as $key) {
                Cache::forget($key);
            }
            Cache::forget($trackerKey);
        } catch (\Exception $e) {
            Log::warning('Cache flush by prefix failed', [
                'prefix' => $prefix,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Register a cache key under its prefix for later flushing.
     *
     * @param string $key
     * @return void
     */
    private function registerKey(string $key): void
    {
        $prefix = explode(':', $key)[0] ?? $key;
        $trackerKey = 'cache_keys_' . $prefix;

        try {
            $keys = Cache::get($trackerKey, []);
            if (!in_array($key, $keys, true)) {
                $keys[] = $key;
                Cache::put($trackerKey, $keys, self::CACHE_VERY_LONG);
            }
        } catch (\Exception $e) {
            // Silently fail — key tracking is non-critical
        }
    }

    /**
     * Cache paket bandwidth list
     *
     * @param callable $callback
     * @return mixed
     */
    public function cachePaketList(callable $callback)
    {
        return $this->remember(
            self::PREFIX_PAKET . ':list',
            self::CACHE_LONG,
            $callback
        );
    }

    /**
     * Cache pelanggan list with paket
     *
     * @param callable $callback
     * @return mixed
     */
    public function cachePelangganList(callable $callback)
    {
        return $this->remember(
            self::PREFIX_PELANGGAN . ':list',
            self::CACHE_SHORT,
            $callback
        );
    }

    /**
     * Cache single pelanggan data
     *
     * @param int $id
     * @param callable $callback
     * @return mixed
     */
    public function cachePelanggan(int $id, callable $callback)
    {
        return $this->remember(
            self::PREFIX_PELANGGAN . ':' . $id,
            self::CACHE_SHORT,
            $callback
        );
    }

    /**
     * Cache MikroTik system summary
     *
     * @param callable $callback
     * @return mixed
     */
    public function cacheMikrotikSummary(callable $callback)
    {
        return $this->remember(
            self::PREFIX_MIKROTIK . ':summary',
            self::CACHE_MEDIUM,
            $callback
        );
    }

    /**
     * Cache MikroTik realtime stats (short TTL)
     *
     * @param callable $callback
     * @return mixed
     */
    public function cacheMikrotikRealtimeStats(callable $callback)
    {
        return $this->remember(
            self::PREFIX_MIKROTIK . ':realtime',
            60, // 1 minute only for realtime data
            $callback
        );
    }

    /**
     * Cache dashboard statistics
     *
     * @param callable $callback
     * @return mixed
     */
    public function cacheDashboardStats(callable $callback)
    {
        return $this->remember(
            self::PREFIX_DASHBOARD . ':stats',
            self::CACHE_SHORT,
            $callback
        );
    }

    /**
     * Invalidate pelanggan cache
     *
     * @param int|null $id
     * @return void
     */
    public function invalidatePelanggan(?int $id = null): void
    {
        $this->forget(self::PREFIX_PELANGGAN . ':list');
        
        if ($id) {
            $this->forget(self::PREFIX_PELANGGAN . ':' . $id);
        }
        
        // Also invalidate dashboard stats as they depend on pelanggan
        $this->forget(self::PREFIX_DASHBOARD . ':stats');
    }

    /**
     * Invalidate paket cache
     *
     * @return void
     */
    public function invalidatePaket(): void
    {
        $this->forget(self::PREFIX_PAKET . ':list');
        $this->forget(self::PREFIX_PELANGGAN . ':list');
    }

    /**
     * Invalidate MikroTik cache
     *
     * @return void
     */
    public function invalidateMikrotik(): void
    {
        $this->forget(self::PREFIX_MIKROTIK . ':summary');
        $this->forget(self::PREFIX_MIKROTIK . ':realtime');
    }

    /**
     * Invalidate all cache
     *
     * @return void
     */
    public function invalidateAll(): void
    {
        try {
            Cache::flush();
        } catch (\Exception $e) {
            Log::warning('Cache flush all failed', [
                'error' => $e->getMessage()
            ]);
        }
    }
}
