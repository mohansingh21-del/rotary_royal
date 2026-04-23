<?php

namespace App\Notifications;

use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class EventAdminNotification extends Notification
{
    use Queueable;

    protected $event;
    protected $action;

    public function __construct(Event $event, string $action)
    {
        $this->event = $event;
        $this->action = $action;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        return [
            'event_id' => $this->event->id,
            'event_name' => $this->event->name,
            'action' => $this->action,
            'is_active' => $this->event->is_active,
            'message' => 'Event "' . $this->event->name . '" ' . $this->action . '.',
            'type' => 'event_' . $this->action,
        ];
    }
}
