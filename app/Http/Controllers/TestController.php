<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class TestController extends Controller
{
    public  function test()
    {

        try {

            Mail::raw('Laravel 13 Gmail SMTP test email.', function ($message) {

                $message->to('pocotilencovv@gmail.com')
                        ->subject('Laravel 13 SMTP Test');

            });

            echo 'Email sent successfully';

        } catch (\Exception $e) {

            echo $e->getMessage();

        }
    }

}
