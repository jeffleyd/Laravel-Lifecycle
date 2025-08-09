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

        Cache::forget($cacheKey);

        $deleted = 0;
        $tags = Cache::tags(['lifecycle']);
        if (method_exists($tags, 'flush')) {
            $tags->flush();
            $deleted = 'all tagged';
        } else {
            try {
                $keys = Cache::getStore()->keys($cacheKey . '.*');
                foreach ($keys as $key) {
                    Cache::forget($key);
                    $deleted++;
                }
            } catch (\Exception $e) {
                Cache::forget($cacheKey);
                $deleted = 1;
            }
        }
        
        $this->info("Lifecycle hooks cache cleared! ({$deleted} entries removed)");
        
        return Command::SUCCESS;
    }
}