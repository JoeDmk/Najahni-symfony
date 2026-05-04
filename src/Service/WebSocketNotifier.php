<?php

namespace App\Service;

/**
 * Sends push notifications to the WebSocket server via internal TCP.
 * The WS server then relays them to the connected browser client(s).
 */
class WebSocketNotifier
{
    private string $pushHost;
    private int $pushPort;

    public function __construct(string $pushHost = '127.0.0.1', int $pushPort = 8091)
    {
        $this->pushHost = $pushHost;
        $this->pushPort = $pushPort;
    }

    /**
     * Push a notification to a specific user via WebSocket.
     *
     * @param int    $userId  Target user ID
     * @param array  $notification  Notification data (title, message, type, etc.)
     */
    public function pushNotification(int $userId, array $notification): bool
    {
        $payload = json_encode([
            'userId' => $userId,
            'payload' => array_merge([
                'type' => 'notification',
            ], $notification),
        ]);

        return $this->sendToPushServer($payload);
    }

    private function sendToPushServer(string $data): bool
    {
        $socket = @stream_socket_client(
            "tcp://{$this->pushHost}:{$this->pushPort}",
            $errno, $errstr, 2
        );

        if (!$socket) {
            // WebSocket server not running — silently fail
            return false;
        }

        @fwrite($socket, $data);
        @fclose($socket);

        return true;
    }
}
