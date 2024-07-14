<?php

namespace App\Proxies;

use App\Proxies\Models\ProxiesModel;
use App\Proxies\Protocol\Server\Socks5Protocol;
use Closure;
use Psc\Core\Stream\Stream;
use Throwable;
use function P\repeat;

class Inspector
{
    private int $running = 0;

    public function __construct(private readonly int $concurrent)
    {

    }

    /**
     * @return void
     */
    public function run(): void
    {
        ProxiesModel::whereStatus(2)->update(['update_time' => 0, 'status' => 0]);
        $this->inspectorQueue();
    }

    /**
     * @return void
     */
    public function inspectorQueue(): void
    {
        repeat(1, function () {
            ProxiesModel::whereStatus(0)->where('update_time', '<', time() - 10)
                ->limit($this->concurrent - $this->running)
                ->orderBy('update_time', 'asc')
                ->each(function (ProxiesModel $proxiesModel) {
                    $this->inspectorHandle($proxiesModel);
                });

            ProxiesModel::whereStatus(1)->where('update_time', '<', time() - 60)
                ->limit($this->concurrent - $this->running)
                ->orderBy('update_time', 'asc')
                ->each(function (ProxiesModel $proxiesModel) {
                    $this->inspectorHandle($proxiesModel);
                });

            ProxiesModel::whereStatus(-1)->where('update_time', '<', time() - 10)
                ->limit($this->concurrent - $this->running)
                ->orderBy('update_time', 'asc')
                ->each(function (ProxiesModel $proxiesModel) {
                    $this->inspectorHandle($proxiesModel);
                });
        });
    }

    /**
     * @param ProxiesModel $proxiesModel
     * @return void
     */
    public function inspectorHandle(ProxiesModel $proxiesModel): void
    {
        $this->running++;
        $proxiesModel->status      = 2;
        $proxiesModel->update_time = time();
        $proxiesModel->save();
        $timeBefore = microtime(true);

        switch ($proxiesModel->protocol) {
            case 'socks5':
                $proxiesStream = stream_socket_client(
                    "tcp://{$proxiesModel->host}:{$proxiesModel->port}",
                    $_,
                    $_,
                    3,
                    STREAM_CLIENT_ASYNC_CONNECT | STREAM_CLIENT_CONNECT
                );

                $proxies = new Stream($proxiesStream);
                $proxies->setBlocking(false);

                $protocol = new Socks5Protocol($proxies, []);

                $protocol->onHandshake = fn() => $proxiesModel->valid(intval((microtime(true) - $timeBefore) * 1000));
                $protocol->onRejection = fn() => $proxiesModel->invalid();
                $protocol->onClose     = fn() => $proxiesModel->invalid();

                $proxies->onWritable(function (Stream $proxies, Closure $cancel) use ($protocol) {
                    $cancel();
                    try {
                        $protocol->onConnected();
                    } catch (Throwable $e) {
                        $proxies->close();
                        $protocol->onBreak();
                    }
                });

                break;
            default:
                $proxiesModel->invalid();
                break;
        }
    }
}
