<?php

namespace App\Proxies;

use App\Proxies\Models\ProxiesModel;
use P\System;
use Psc\Store\System\Exception\ProcessException;

readonly class Extractor
{
    public function __construct(private int $count)
    {
    }

    /**
     * @return void
     * @throws ProcessException
     */
    public function run(): void
    {
        $task = System::Process()->task(fn() => $this->loop());
        $task->run();
    }

    /**
     * @return void
     */
    public function loop(): void
    {
        while (true) {
            $count = ProxiesModel::whereIn('status', [0, 1, 2])->count();
            $diff  = $this->count - $count;
            sleep(1);
        }
    }
}
