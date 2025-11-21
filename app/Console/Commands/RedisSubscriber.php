<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Models\MessageStatus;
use Predis\Client as PredisClient;

class RedisSubscriber extends Command
{
    protected $signature = 'redis:subscribe';
    protected $description = 'Subscribe to Redis channels for message status updates';

    public function handle()
    {
        $this->info('🎧 Listening to Redis channels...');

        try {
            $redis = new PredisClient([
                'scheme' => 'tcp',
                'host'   => env('REDIS_HOST', 'redis'),
                'port'   => env('REDIS_PORT', 6379),
            ]);

            $pubsub = $redis->pubSubLoop();
            $pubsub->subscribe('message-delivered', 'message-read');

            $this->info('✅ Subscribed to Redis channels');

            foreach ($pubsub as $message) {
                if ($message->kind === 'message') {
                    $this->handleMessage($message->channel, $message->payload);
                }
            }
        } catch (\Exception $e) {
            $this->error('❌ Redis connection error: ' . $e->getMessage());
            Log::error('Redis Subscriber Error: ' . $e->getMessage());
            sleep(5);
            $this->handle(); // Retry
        }
    }

    private function handleMessage($channel, $payload)
    {
        try {
            $data = json_decode($payload, true);

            if (!isset($data['messageId']) || !isset($data['userId'])) {
                Log::warning("Invalid message format", ['channel' => $channel, 'payload' => $payload]);
                return;
            }

            $this->info("📨 [{$channel}] Message: {$data['messageId']}, User: {$data['userId']}");

            $this->handleStatusUpdate($data, $channel);
        } catch (\Exception $e) {
            Log::error("Error processing message: " . $e->getMessage(), [
                'channel' => $channel,
                'payload' => $payload
            ]);
        }
    }

    private function handleStatusUpdate($data, $channel)
    {
        $messageId = $data['messageId'];
        $userId = $data['userId'];

        $status = MessageStatus::where('message_id', $messageId)
            ->where('user_id', $userId)
            ->first();

        if (!$status) {
            Log::warning("Status not found for message $messageId, user $userId");
            return;
        }

        $newStatus = null;

        if ($channel === 'message-read' && in_array($status->status, ['sent', 'delivered'])) {
            $status->markAsRead();
            $newStatus = 'read';
            $this->info("👁️ Message $messageId marked as READ");
        } elseif ($channel === 'message-delivered' && $status->status === 'sent') {
            $status->markAsDelivered();
            $newStatus = 'delivered';
            $this->info("✅ Message $messageId marked as DELIVERED");
        }

        if ($newStatus) {
            // Публікуємо оновлення назад
            $redis = app('redis')->connection()->client();
            $redis->publish('message-status', json_encode([
                'messageId' => $messageId,
                'userId' => $status->message->user_id,
                'status' => $newStatus
            ]));

            $this->info("📤 Published status update: $newStatus");
        }
    }
}
