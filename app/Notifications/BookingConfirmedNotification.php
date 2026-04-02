<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingConfirmedNotification extends Notification
{
    use Queueable;

    protected $booking;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(Booking $booking)
    {
        $this->booking = $booking;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Booking Confirmed - ' . $this->booking->id)
            ->line('Your booking for ' . $this->booking->asset->name . ' has been confirmed.')
            ->line('Start Date: ' . $this->booking->start_date->format('d-m-Y H:i'))
            ->line('End Date: ' . $this->booking->end_date->format('d-m-Y H:i'))
            ->line('Thank you for choosing Rotary Royals Emergency Bank.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            'booking_id' => $this->booking->id,
            'asset_name' => $this->booking->asset->name,
            'message' => 'Your booking for ' . $this->booking->asset->name . ' has been confirmed.',
            'type' => 'booking_confirmed',
        ];
    }
}