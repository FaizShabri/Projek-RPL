<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccountApprovedMailable extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public User $user;
    public string $loginUrl;

    public function __construct(User $user, ?string $loginUrl = null)
    {
        $this->user = $user;
        $this->loginUrl = $loginUrl ?: (config('app.frontend_url', 'http://localhost:5173') . '/login');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Pendaftaran Akun Disetujui - Pusat Laundry',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.account-approved',
        );
    }
}
