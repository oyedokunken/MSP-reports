<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

final class AIWatcherMessage extends Notification
{
    use Queueable;

    /** @var array<string, mixed> */
    protected array $data;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (!in_array(config('broadcasting.default'), ['log', 'null', null])) {
            $channels[] = 'broadcast';
        }

        return $channels;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'ai_watcher',
            'severity' => $this->data['severity'] ?? 'blue',
            'title' => $this->data['title'] ?? '🤖 AI Assistant',
            'description' => $this->data['description'] ?? '',
            'metadata' => $this->data['metadata'] ?? [],
        ];
    }

    /**
     * Get the broadcastable representation of the notification.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'id' => $this->id,
            'type' => 'ai_watcher',
            'severity' => $this->data['severity'] ?? 'blue',
            'title' => $this->data['title'] ?? '🤖 AI Assistant',
            'description' => $this->data['description'] ?? '',
            'created_at' => now()->toIso8601String(),
            'read' => false,
            'metadata' => $this->data['metadata'] ?? [],
        ]);
    }
}
