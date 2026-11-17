<?php

namespace App\Mail;

use App\Models\StaffInvitation;
use App\Models\Toko;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StaffInvitationMailable extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public User $inviter;
    public string $roleLabel;
    public ?Toko $toko;
    public string $inviteUrl;
    public string $expiresAt;

    public function __construct(User $inviter, string $role, ?Toko $toko, string $inviteUrl, string $expiresAt)
    {
        $this->inviter = $inviter;
        $this->roleLabel = match ($role) {
            'admin_owner' => 'Admin Owner (Moderasi Platform)',
            'admin_toko' => 'Admin Toko (Staf Operasional Toko)',
            'kurir' => 'Mitra Kurir Laundry',
            default => ucwords(str_replace('_', ' ', $role)),
        };
        $this->toko = $toko;
        $this->inviteUrl = $inviteUrl;
        $this->expiresAt = $expiresAt;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Undangan Bergabung sebagai {$this->roleLabel} - Pusat Laundry",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.staff-invitation',
        );
    }
}
