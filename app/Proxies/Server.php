<?php

namespace App\Proxies;

use App\Proxies\Models\ProxiesModel;
use App\Proxies\Protocol\Server\Socks5Protocol;
use Closure;
use Exception;
use P\IO;
use Psc\Core\Output;
use Psc\Core\Stream\Stream;
use Psc\Store\System\Exception\ProcessException;
use Throwable;
use function P\async;
use function P\await;
use function P\cancel;
use function P\run;

readonly class Server
{
    /**
     * @param string $listenAddress
     * @param array  $config
     */
    public function __construct(private string $listenAddress, private array $config = [])
    {
    }

    /**
     * @param string $proxies
     * @return ProxiesModel
     * @throws Exception
     */
    public static function push(string $proxies): ProxiesModel
    {
        Output::info("[Push] {$proxies}");
        $explode = explode('://', $proxies);
        if (count($explode) !== 2) {
            throw new Exception('proxies format error');
        }
        $scheme  = $explode[0];
        $proxies = $explode[1];
        $explode = explode(':', $proxies);
        if (count($explode) !== 2) {
            throw new Exception('proxies format error');
        }
        $host = $explode[0];
        $port = $explode[1];
        if ($origin = ProxiesModel::whereHost($host)->wherePort($port)->whereProtocol($scheme)->first()) {
            $origin->status = 0;
            $origin->fail   = 0;
            $origin->save();
            return $origin;
        }
        $proxiesModel           = new ProxiesModel();
        $proxiesModel->protocol = $scheme;
        $proxiesModel->host     = $host;
        $proxiesModel->port     = $port;
        $proxiesModel->status   = 0;
        $proxiesModel->save();
        return $proxiesModel;
    }

    /**
     * @return void
     * @throws ProcessException
     */
    public function launch(): void
    {
        $task = \P\System::Process()->task(function () {
            $inspector = new Inspector($this->config['concurrent'] ?? 600);
            $inspector->run();
        });
        $task->run();

        $extractor = new Extractor($this->config['count'] ?? 600);
        $extractor->run();

        try {
            $this->listenHttp();
            run();
        } catch (Throwable $e) {
            Output::exception($e);
            exit;
        }
    }

    /**
     * @return void
     */
    private function listenHttp(): void
    {
        $parse        = parse_url($this->listenAddress);
        $host         = $parse['host'];
        $port         = $parse['port'];
        $listenStream = stream_socket_server("tcp://{$host}:{$port}", $_, $_);
        $listenSocket = socket_import_stream($listenStream);
        socket_set_option($listenSocket, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_set_option($listenSocket, SOL_SOCKET, SO_REUSEPORT, 1);
        $listen = new Stream($listenStream);
        $listen->setBlocking(false);
        $listen->onReadable(function (Stream $listen) {
            async(function () use ($listen) {
                $clientStream = stream_socket_accept($listen->stream);
                $client       = new Stream($clientStream);
                $client->setBlocking(false);
                if (!$proxiesModel = ProxiesModel::shift()) {
                    $client->close();
                    return;
                }
                $config   = $proxiesModel->toArray();
                $protocol = $config['protocol'];
                $host     = $config['host'];
                $port     = $config['port'];

                try {
                    /**
                     * @var Stream $server
                     */
                    $server = await(IO::Socket()->streamSocketClient("tcp://{$host}:{$port}", 3));
                } catch (Throwable $e) {
                    $client->close();
                    return;
                }

                switch ($protocol) {
                    case 'socks5':
                        $protocol = new Socks5Protocol($server, $config);
                        break;

                    default:
                        $client->close();
                        return;
                }

                // 绑定关闭事件
                $client->onClose(fn() => $server->close());
                $server->onClose(fn() => $client->close());
                $server->onClose(fn() => $protocol->onBreak());

                $clientBuffer = '';

                $protocol->onHandshake = function () use (&$clientBuffer, $protocol, $server, $client, $config) {
                    $client->onReadable(function (Stream $client, Closure $cancel) use (&$clientBuffer, $protocol, $server, $config) {
                        $content = $client->read(8192);
                        if ($content === '') {
                            $client->close();
                            $cancel();
                            return;
                        }

                        $clientBuffer .= $content;

                        if (str_contains($clientBuffer, "\r\n\r\n")) {
                            $httpsMatch = preg_match('/^CONNECT ([^ ]+) HTTP\/1.[01]/', $clientBuffer, $httpsMatches);
                            $httpMatch  = preg_match('/\nHost: ([^\n]+)\n/', $clientBuffer, $httpMatches);

                            if (isset($this->config['username']) && isset($this->config['password'])) {
                                $authMatch    = preg_match('/Proxy-Authorization: Basic ([^\r\n]+)/', $clientBuffer, $authMatches);
                                $username = $this->config['username'];
                                $password = $this->config['password'];
                                $expectedAuth = base64_encode("$username:$password");

                                if (!isset($authMatches[1]) || $authMatches[1] !== $expectedAuth) {
                                    $client->write("HTTP/1.1 407 Proxy Authentication Required\r\nProxy-Authenticate: Basic realm=\"Proxy\"\r\n\r\n");
                                    $client->close();
                                    $cancel();
                                    return;
                                }
                            }

                            if ($httpsMatch) {
                                $explode = trim($httpsMatches[1]);
                                $explode = explode(':', $explode);

                                $host = $explode[0];
                                $port = $explode[1] ?? 443;

                                $protocol->bind([
                                    'host' => $host,
                                    'port' => $port,
                                ]);

                                try {
                                    $client->write("HTTP/1.1 200 Connection Established\r\n\r\n");
                                } catch (Throwable $e) {
                                    $client->close();
                                    $cancel();
                                }

                                $clientBuffer = '';
                            } elseif ($httpMatch) {
                                $explode = trim($httpMatches[1]);
                                $explode = explode(':', $explode);
                                $host    = $explode[0];
                                $port    = $explode[1] ?? 80;

                                $protocol->bind([
                                    'host' => $host,
                                    'port' => $port,
                                ]);
                            } else {
                                try {
                                    $client->write("HTTP/1.1 400 Bad Request\r\n\r\n");
                                } catch (Throwable $e) {
                                }
                                $client->close();
                                $cancel();
                            }
                            $cancel();
                        }
                    });
                };

                $protocol->onBind = function () use (&$clientBuffer, $server, $client, $config) {
                    if ($clientBuffer) {
                        $server->write($clientBuffer);
                        $clientBuffer = '';
                    }

                    $this->transfer($server, $client);
                    $this->transfer($client, $server);
                    Output::info("[Connected] {$config['host']}:{$config['port']}");
                };

                try {
                    $protocol->onConnected();
                } catch (Throwable $e) {
                    $server->close();
                    return;
                }
            });
        });
    }

    /**
     * @param Stream $client
     * @param Stream $target
     * @return void
     */
    private function transfer(Stream $client, Stream $target): void
    {
        $cancelId = $client->onReadable(function (Stream $client, Closure $cancel) use ($target) {
            try {
                $content = $client->read(8192);
                if ($content === '') {
                    $client->close();
                    $cancel();
                    return;
                }
                $target->write($content);
            } catch (Throwable $e) {
                $target->close();
                $cancel();
            }
        });
        $target->onClose(fn() => cancel($cancelId));
    }
}
