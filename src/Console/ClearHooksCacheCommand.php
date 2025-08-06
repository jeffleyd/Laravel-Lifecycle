<?php

namespace PhpDiffused\Lifecycle\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ClearHooksCacheCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lifecycle:clear-cache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear the lifecycle hooks cache';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $cacheKey = config('lifecycle.cache.key', 'lifecycle.hooks');
        
        // Clear all cached hooks
        Cache::forget($cacheKey);
        
        // Clear specific service caches if they exist
        $deleted = 0;
        $tags = Cache::tags(['lifecycle']);
        if (method_exists($tags, 'flush')) {
            $tags->flush();
            $deleted = 'all tagged';
        } else {
            // Fallback: try to clear by pattern if cache driver supports it
            try {
                $keys = Cache::getStore()->keys($cacheKey . '.*');
                foreach ($keys as $key) {
                    Cache::forget($key);
                    $deleted++;
                }
            } catch (\Exception $e) {
                // Some cache drivers don't support pattern deletion
                Cache::forget($cacheKey);
                $deleted = 1;
            }
        }
        
        $this->info("Lifecycle hooks cache cleared! ({$deleted} entries removed)");
        
        return Command::SUCCESS;
    }
}