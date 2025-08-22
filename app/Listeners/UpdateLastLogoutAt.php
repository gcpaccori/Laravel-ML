<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Logout;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Stevebauman\Location\Facades\Location;
use App\Models\SessionLog;

class UpdateLastLogoutAt
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
    public function handle(Logout $event): void
    {
        $ip = request()->ip();
        $userAgent = request()->userAgent();
        $location = Location::get( $ip );

        SessionLog::create([
            'user_id' => $event->user->id,
            'location' => $location ? json_encode($location) : null,
            'status' => 'logout',
            'user_agent' => $userAgent,
            'ip_address' => $ip,
            'last_activity' => now()
        ]);

        $event->user->update([
            'last_login_at' => now()
        ]);
    }
}
