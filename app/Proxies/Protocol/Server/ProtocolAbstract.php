<?php

namespace App\Proxies\Protocol\Server;

use Closure;
use Psc\Core\Stream\Stream;
use Throwable;
use function P\cancel;

abstract class ProtocolAbstract
{
    public Closure $onConnected;
    public Closure $onHandshake;
    public Closure $onBind;
    public Closure $onRejection;
    public Closure $onClose;

    protected Stream $stream;

    protected array $payload;


    private string $tick;

    /**
     * @param Stream $stream
     * @param array  $payload
     */
    abstract public function __construct(Stream $stream, array $payload);

    /**
     * @param array $payload
     * @return bool
     */
    abstract public function bind(array $payload): bool;

    /**
     * @return void
     */
    public function onConnected(): void
    {
        if (isset($this->onConnected)) {
            call_user_func($this->onConnected, $this);
        }

        $this->tick = $this->stream->onReadable(function (Stream $server, Closure $cancel) {
            try {
                $content = $server->read(8192);
                if ($content === '') {
                    $server->close();
                    $this->onBreak();
                    $cancel();
                    return;
                }
                $this->tick($content);
            } catch (Throwable $e) {
                $server->close();
                $cancel();
            }
        });
    }

    /**
     * @return void
     */
    public function onBreak(): void
    {
        if (isset($this->onClose)) {
            call_user_func($this->onClose, $this);
        }
    }

    /**
     * @param string $content
     * @return void
     */
    abstract public function tick(string $content): void;

    /**
     * @return void
     */
    protected function onReject(): void
    {
        if (isset($this->onRejection)) {
            call_user_func($this->onRejection, $this);
        }
    }

    /**
     * @return void
     */
    protected function onHandshake(): void
    {
        if (isset($this->onHandshake)) {
            call_user_func($this->onHandshake, $this);
        }
    }

    /**
     * @return void
     */
    protected function onBind(): void
    {
        if (isset($this->onBind)) {
            call_user_func($this->onBind, $this);
        }
        cancel($this->tick);
    }
}
