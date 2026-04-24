<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingSubmittedNotification extends Notification
{
    use Queueable;

    protected $booking;

    public function __construct(Booking $booking)
    {
        $this->booking = $booking;
    }

    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Booking Submitted - ' . $this->booking->id)
            ->line('Your booking request for ' . $this->booking->asset->name . ' has been submitted successfully.')
            ->line('Start Date: ' . $this->booking->start_date->format('d-m-Y H:i'))
            ->line('End Date: ' . $this->booking->end_date->format('d-m-Y H:i'))
            ->line('Current Status: ' . $this->booking->status)
            ->line('We will notify you once your booking is reviewed.');
    }

    public function toArray($notifiable)
    {
        return [
            'booking_id' => $this->booking->id,
            'asset_name' => $this->booking->asset->name,
            'status' => $this->booking->status,
            'message' => 'Your booking request for ' . $this->booking->asset->name . ' has been submitted successfully.',
            'type' => 'booking_submitted',
        ];
    }
}
