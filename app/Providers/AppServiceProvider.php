<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        
        ResetPassword::createUrlUsing(function ($user, string $token) {
            return "http://localhost:5173/reset-password?token=$token&email=" . urlencode($user->email);
        });

        VerifyEmail::createUrlUsing(function ($notifiable) {
            return "http://localhost:5173/verify-email?id=" 
                 . $notifiable->id 
                 . "&hash=" 
                 . sha1($notifiable->getEmailForVerification());
        });
    }
}
