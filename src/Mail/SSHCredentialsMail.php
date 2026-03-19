<?php

namespace Apexsys\ServerMigration\Mail;

use Apexsys\ServerMigration\Models\DedicatedUser;
use Apexsys\ServerMigration\Models\ServerMigration;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SSHCredentialsMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly DedicatedUser   $user,
        public readonly ServerMigration $migration,
        public readonly string          $privateKey,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from:    config('server-migration.mail.from_address'),
            replyTo: config('server-migration.mail.reply_to'),
            subject: 'Server Migration Complete — Your SSH Access Details',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'server-migration::emails.ssh-credentials',
            with: [
                'user'      => $this->user,
                'migration' => $this->migration,
            ],
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(
                fn () => $this->privateKey,
                "id_ed25519_{$this->user->linux_username}"
            )->withMime('application/octet-stream'),
        ];
    }
}
