<?php

namespace App\Notifications;

use App\Models\Donation;
use App\Models\DonationReceipt;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DonationReceiptReadyNotification extends Notification
{
    use Queueable;

    protected $donation;
    protected $receipt;

    public function __construct(Donation $donation, DonationReceipt $receipt)
    {
        $this->donation = $donation;
        $this->receipt = $receipt;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        return [
            'donation_id' => $this->donation->id,
            'receipt_id' => $this->receipt->id,
            'receipt_no' => $this->receipt->receipt_no,
            'receipt_pdf_url' => $this->receipt->pdf_path ? asset($this->receipt->pdf_path) : null,
            'message' => 'Your donation receipt is ready for download.',
            'type' => 'donation_receipt_ready',
        ];
    }
}
