<?php

declare(strict_types=1);

namespace PHPStreamServer\Test\data;

use Amp\Http\Client\Connection\DefaultConnectionFactory;
use Amp\Http\Client\Connection\UnlimitedConnectionPool;
use Amp\Http\Client\HttpClient;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Socket\ClientTlsContext;
use Amp\Socket\ConnectContext;
use Amp\Socket\DnsSocketConnector;
use PHPStreamServer\Core\MessageBus\MessageInterface;
use PHPUnit\Framework\TestCase;

abstract class PHPSSTestCase extends TestCase
{
    /**
     * @template T
     * @param MessageInterface<T> $message
     * @return T
     */
    protected function dispatch(MessageInterface $message): mixed
    {
        $serializedMessage = \base64_encode(\serialize($message));
        $descriptor = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = \proc_open(\phpss_create_command('test-dispatch --message=' . $serializedMessage), $descriptor, $pipes);
        $serializedResponse = \stream_get_contents($pipes[1]);
        $returnCode = \proc_close($process);

        if ($returnCode === 1) {
            $this->fail('Server is not running');
        }

        return \unserialize($serializedResponse);
    }

    protected function createHttpClient(): HttpClient
    {
        $pool = new UnlimitedConnectionPool(new DefaultConnectionFactory(
            connector: new DnsSocketConnector(),
            connectContext: (new ConnectContext())->withTlsContext((new ClientTlsContext())->withoutPeerVerification()),
        ));

        return (new HttpClientBuilder())
            ->usingPool($pool)
            ->retry(0)
            ->build();
    }
}
