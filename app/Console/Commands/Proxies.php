<?php

namespace App\Console\Commands;

use App\Proxies\Server;
use Illuminate\Console\Command;

class Proxies extends Command
{
    protected $signature = 'app:proxies';
    protected $description = 'proxy';

    /**
     * @return void
     */
    public function handle(): void
    {
        ini_set('memory_limit', '4096M');
        $server = new Server('http://127.0.0.1:29980');
        $server->launch();
    }
}
