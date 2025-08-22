<?php

namespace App\Listeners;

use App\Models\SessionLog;
use Illuminate\Auth\Events\Login;
use Illuminate\Queue\InteractsWithQueue;
use Stevebauman\Location\Facades\Location;
use Illuminate\Contracts\Queue\ShouldQueue;

class UpdateLastLoginAt
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(Login $event): void
    {
        $ip = request()->ip();
        $userAgent = request()->userAgent();
        $location = Location::get( $ip );

        SessionLog::create([
            'user_id' => $event->user->id,
            'location' => $location ? json_encode($location) : null,
            'status' => 'login',
            'user_agent' => $userAgent,
            'ip_address' => $ip,
            'last_activity' => now()
        ]);

        // dd($event->user, $ip, $userAgent, $position);

        $event->user->update([
            'last_login_at' => now()
        ]);
    }
}
