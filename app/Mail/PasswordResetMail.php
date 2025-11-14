<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public $resetLink;
    public $userName;

    /**
     * Create a new message instance.
     */
    public function __construct($resetLink, $userName = 'User')
    {
        $this->resetLink = $resetLink;
        $this->userName = $userName;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Password Reset Request')
                    ->view('emails.password-reset')
                    ->with([
                        'resetLink' => $this->resetLink,
                        'userName' => $this->userName
                    ]);
    }
}