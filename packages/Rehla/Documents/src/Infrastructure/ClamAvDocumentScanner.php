<?php

declare(strict_types=1);

namespace Rehla\Documents\Infrastructure;

use Rehla\Documents\Contracts\DocumentScanner;
use Rehla\Documents\Data\ScanResult;
use RuntimeException;

final class ClamAvDocumentScanner implements DocumentScanner
{
    public function scan(string $contents): ScanResult
    {
        $endpoint = (string) config('rehla-documents.clamav.endpoint');
        $timeout = max(0.1, (float) config('rehla-documents.clamav.timeout_seconds', 10));
        $socket = @stream_socket_client($endpoint, $errorCode, $errorMessage, $timeout);
        if ($socket === false) {
            throw new RuntimeException;
        }

        try {
            stream_set_timeout($socket, (int) ceil($timeout));
            $this->write($socket, "zINSTREAM\0");
            foreach (str_split($contents, 8192) as $chunk) {
                $this->write($socket, pack('N', strlen($chunk)).$chunk);
            }
            $this->write($socket, pack('N', 0));

            $response = stream_get_contents($socket, 4096);
            if (! is_string($response)) {
                throw new RuntimeException;
            }
        } finally {
            fclose($socket);
        }

        if (str_contains($response, ' FOUND')) {
            return ScanResult::rejected('malware');
        }
        if (str_contains($response, ' OK')) {
            return ScanResult::clean();
        }

        throw new RuntimeException;
    }

    /** @param resource $socket */
    private function write($socket, string $payload): void
    {
        $offset = 0;
        while ($offset < strlen($payload)) {
            $written = fwrite($socket, substr($payload, $offset));
            if ($written === false || $written === 0) {
                throw new RuntimeException;
            }
            $offset += $written;
        }
    }
}
