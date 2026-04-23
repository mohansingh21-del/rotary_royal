<?php

namespace App\Notifications;

use App\Models\Donation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DonationReceivedAdminNotification extends Notification
{
    use Queueable;

    protected $donation;

    public function __construct(Donation $donation)
    {
        $this->donation = $donation;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        return [
            'donation_id' => $this->donation->id,
            'donor_name' => $this->donation->donor_name,
            'amount' => $this->donation->amount,
            'project_name' => $this->donation->project?->name,
            'message' => 'New donation received from ' . $this->donation->donor_name . ' for Rs. ' . number_format((float) $this->donation->amount, 2),
            'type' => 'donation_received',
        ];
    }
}
