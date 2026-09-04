<?php

namespace Pterodactyl\Notifications;

use Pterodactyl\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class AccountCreated extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public User $user, public ?string $token = null)
    {
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(): MailMessage
    {
        $message = (new MailMessage())
            ->greeting('Hello ' . $this->user->name . '!')
            ->line('You are receiving this email because an account has been created for you on ' . config('app.name') . '.')
            ->line('Username: ' . $this->user->username)
            ->line('Email: ' . $this->user->email);

        $authentik = config('services.authentik');
        if (!empty($authentik['client_id']) && !empty($authentik['base_url'])) {
            // The panel reset link would set a password the SSO-only sign-in
            // never reads, and linking to Authentik instead would be worse: the
            // directory account does not exist until provisioning has run.
            return $message->line(
                'You sign in with the Single Sign-On button - there is no separate password for this panel. '
                . 'Your sign-in details are set up separately and you will be contacted once your account is ready.'
            );
        }

        if (!is_null($this->token)) {
            return $message->action('Setup Your Account', url('/auth/password/reset/' . $this->token . '?email=' . urlencode($this->user->email)));
        }

        return $message;
    }
}
