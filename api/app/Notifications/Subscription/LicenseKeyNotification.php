<?php

namespace App\Notifications\Subscription;

use App\Models\LicenseKey;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LicenseKeyNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private LicenseKey $licenseKey)
    {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Your OpnForm Enterprise License Key')
            ->greeting('Thank you for purchasing OpnForm Enterprise!')
            ->line('Your license key is ready. Copy it and enter it in your self-hosted instance to activate Enterprise features.')
            ->line('**Your License Key:**')
            ->line('`' . $this->licenseKey->license_key . '`')
            ->line('**Click below to activate your license:**')
            ->action('Activate License', front_url('home') . '?user-settings=license');
    }
}
