<?php

namespace App\Jobs;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $credentials;

    public function __construct($credentials)
    {
        $this->credentials=$credentials;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Log::info('after'.Carbon::now());
        $name=$this->credentials->name;
        $email=$this->credentials->email;
        $token=$this->credentials->token;
        $url=$this->credentials->url;
        $subject=$this->credentials->subject;

        $beautymail = app()->make(\Snowfire\Beautymail\Beautymail::class);
        $beautymail->send('email.verify', ['name' => $name, 'verification_code' => $token   , 'url' => $url],
            function($mail) use ($email, $name, $subject){
                $mail->to($email, $name);
                $mail->subject($subject);
            });
    }
}
