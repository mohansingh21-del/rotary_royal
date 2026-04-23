<?php

namespace App\Notifications;

use App\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ProjectAdminNotification extends Notification
{
    use Queueable;

    protected $project;
    protected $action;

    public function __construct(Project $project, string $action)
    {
        $this->project = $project;
        $this->action = $action;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        return [
            'project_id' => $this->project->id,
            'project_name' => $this->project->name,
            'action' => $this->action,
            'is_active' => $this->project->is_active,
            'message' => 'Project "' . $this->project->name . '" ' . $this->action . '.',
            'type' => 'project_' . $this->action,
        ];
    }
}
