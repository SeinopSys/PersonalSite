<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class ClearRedisKeys extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:clear-redis';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear the cached commit information';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $result = Redis::del(\App\Util\Core::COMMIT_INFO_REDIS_KEY);
        echo "$result ".Str::plural('key', $result)." deleted successfully.\n";
    }
}
