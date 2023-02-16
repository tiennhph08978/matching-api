<?php

namespace App\Mail\Admin\User;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MailStore extends Mailable
{
    use Queueable, SerializesModels;

    protected $data;
    protected $role;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($data, $role)
    {
        $this->data = $data;
        $this->role = $role;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject(trans('mail.subject.store_user'))->view('admin.mail.user.store', ['data' => $this->data, 'role' => $this->role]);
    }
}
