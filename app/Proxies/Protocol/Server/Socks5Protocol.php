<?php

namespace App\Proxies\Protocol\Server;

use Psc\Core\Stream\Stream;

/**
 *
 */
class Socks5Protocol extends ProtocolAbstract
{
    protected int    $step   = 0;
    protected string $buffer = '';

    /**
     * @param Stream $stream
     * @param array  $payload
     */
    public function __construct(Stream $stream, array $payload)
    {
        $this->stream  = $stream;
        $this->payload = $payload;
    }

    /**
     * @return void
     */
    public function onConnected(): void
    {
        $this->sendInitialHandshake();
        parent::onConnected();
    }

    /**
     * @param array $payload
     * @return bool
     */
    public function bind(array $payload): bool
    {
        // Ensure current step allows binding
        if ($this->step !== 2) {
            if (isset($this->onRejection)) {
                call_user_func($this->onRejection, $this);
            }
            return false;
        }
        $this->sendBindRequest($payload['host'], $payload['port']);
        return true;
    }


    /**
     * @param string $content
     * @return void
     */
    public function tick(string $content): void
    {
        $this->buffer .= $content;
        switch ($this->step) {
            case 1:
                if (strlen($this->buffer) < 2) {
                    return;
                }
                $response     = substr($this->buffer, 0, 2);
                $this->buffer = substr($this->buffer, 2);
                if ($response === "\x05\x00") {
                    $this->step = 2;
                    $this->onHandshake();
                    return;
                } elseif ($response === "\x05\x02") {
                    if (isset($this->payload['username'], $this->payload['password'])) {
                        $this->sendAuthRequest(
                            $this->payload['username'] ?? '',
                            $this->payload['password'] ?? ''
                        );
                        $this->step = 3;
                    } else {
                        $this->onReject();
                        return;
                    }
                } else {
                    $this->onReject();
                    return;
                }
                return;

            case 2:
                // Await bind request response
                if (strlen($this->buffer) < 10) {
                    return;
                }
                $response     = substr($this->buffer, 0, 10);
                $this->buffer = substr($this->buffer, 10);
                if ($response[1] === "\x00") {
                    $this->step = 4;
                    $this->onBind();
                } else {
                    $this->onReject();
                }
                return;

            case 3:
                // Await authentication response
                if (strlen($this->buffer) < 2) {
                    return;
                }
                $response     = substr($this->buffer, 0, 2);
                $this->buffer = substr($this->buffer, 2);
                if ($response === "\x01\x00") {
                    $this->step = 2;
                    $this->onHandshake();
                } else {
                    $this->onReject();
                }
                return;
            default:
                if (isset($this->onRejection)) {
                    call_user_func($this->onRejection, $this);
                }
        }
    }

    /**
     * @return void
     */
    private function sendInitialHandshake(): void
    {
        $request = "\x05\x01\x00";
        $this->stream->write($request);
        $this->step = 1;
    }

    /**
     * @param string $username
     * @param string $password
     * @return void
     */
    private function sendAuthRequest(string $username, string $password): void
    {
        $request = "\x01" . chr(strlen($username)) . $username . chr(strlen($password)) . $password;
        $this->stream->write($request);
        $this->step = 3;
    }


    /**
     * @param string $host
     * @param int    $port
     * @return void
     */
    private function sendBindRequest(string $host, int $port): void
    {
        $hostLen    = chr(strlen($host));
        $portPacked = pack('n', $port);
        $request    = "\x05\x01\x00\x03" . $hostLen . $host . $portPacked;
        $this->stream->write($request);
        $this->step = 2;
    }
}
