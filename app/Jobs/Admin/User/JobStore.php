<?php

namespace App\Jobs\Admin\User;

use App\Mail\Admin\User\MailStore;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class JobStore implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $data;
    protected $role;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($data, $role)
    {
        $this->data = $data;
        $this->role = $role;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Mail::to($this->data['email'])->send(new MailStore($this->data, $this->role));
    }
}
