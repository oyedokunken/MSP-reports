<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class ReportDelivered extends Notification implements ShouldBroadcastNow
{
    use Queueable;

    protected array $data;

    /**
     * Create a new notification instance.
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
            'type' => 'report_delivered',
            'severity' => 'green',
            'title' => $this->data['title'] ?? 'Report Delivered',
            'description' => $this->data['description'] ?? 'Your report has been delivered successfully.',
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
            'type' => 'report_delivered',
            'severity' => 'green',
            'title' => $this->data['title'] ?? 'Report Delivered',
            'description' => $this->data['description'] ?? 'Your report has been delivered successfully.',
            'created_at' => now()->toIso8601String(),
            'read' => false,
            'metadata' => $this->data['metadata'] ?? []
        ]);
    }
}
