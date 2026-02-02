<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

final class SystemAlert extends Notification implements ShouldBroadcastNow
{
    use Queueable;

    /** @var array<string, mixed> */
    protected array $data;

    /**
     * Create a new notification instance.
     * 
     * @param array<string, mixed> $data
     */
    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'alert',
            'severity' => $this->data['severity'] ?? 'yellow',
            'title' => $this->data['title'] ?? 'System Alert',
            'description' => $this->data['description'] ?? 'A system event requires your attention.',
            'metadata' => $this->data['metadata'] ?? []
        ];
    }

    /**
     * Get the broadcastable representation of the notification.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'id' => $this->id,
            'type' => 'alert',
            'severity' => $this->data['severity'] ?? 'yellow',
            'title' => $this->data['title'] ?? 'System Alert',
            'description' => $this->data['description'] ?? 'A system event requires your attention.',
            'created_at' => now()->toIso8601String(),
            'read' => false,
            'metadata' => $this->data['metadata'] ?? []
        ]);
    }
}
