<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\HttpServer;

final readonly class Listen
{
    public string $host;

    /**
     * @var int<0, 65535>
     */
    public int $port;

    public function __construct(
        string $listen,
        public bool $tls = false,
        public string|null $tlsCertificate = null,
        public string|null $tlsCertificateKey = null,
    ) {
        $p = \parse_url('tcp://' . $listen);
        $host = $p['host'] ?? $listen;
        $port = $p['port'] ?? ($tls ? 443 : 80);

        if (\str_contains($listen, '://')) {
            throw new \InvalidArgumentException('Listen address should not contain schema');
        }

        if ($port < 0 || $port > 65535) {
            throw new \InvalidArgumentException('Port number must be an integer between 0 and 65535; got ' . $port);
        }

        if ($tls && $tlsCertificate === null) {
            throw new \InvalidArgumentException('Certificate file must be provided');
        }

        $this->host = $host;
        $this->port = $port;
    }

    public function getAddress(): string
    {
        return ($this->tls ? 'https://' : 'http://') . $this->host .
            (($this->tls && $this->port === 443) || (!$this->tls && $this->port === 80) ? '' : ':' . $this->port);
    }
}
