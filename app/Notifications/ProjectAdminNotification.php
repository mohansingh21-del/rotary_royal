<?php

namespace App\Notifications;

use App\Models\Project;
use Carbon\Carbon;
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
        $startDate = $this->project->start_date
            ? Carbon::parse($this->project->start_date)->format('d-m-Y')
            : null;
        $endDate = $this->project->end_date
            ? Carbon::parse($this->project->end_date)->format('d-m-Y')
            : null;

        $dateRange = null;

        if ($startDate && $endDate) {
            $dateRange = ' from ' . $startDate . ' to ' . $endDate;
        } elseif ($startDate) {
            $dateRange = ' from ' . $startDate;
        } elseif ($endDate) {
            $dateRange = ' to ' . $endDate;
        }

        return [
            'project_id' => $this->project->id,
            'project_name' => $this->project->name,
            'action' => $this->action,
            'is_active' => $this->project->is_active,
            'message' => 'Project "' . $this->project->name . '" ' . $this->action . ($dateRange ?? '') . '.',
            'type' => 'project_' . $this->action,
        ];
    }
}
