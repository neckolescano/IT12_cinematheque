<?php

namespace App\Http\Middleware;

use App\Services\UnpaidReservationExpiry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Expires overdue unpaid bookings before the request is handled, so seat maps, seat counts,
 * booking validation and staff pages never see a hold that should already have lapsed.
 */
class ExpireUnpaidReservations
{
    public function __construct(private readonly UnpaidReservationExpiry $expiry)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $this->expiry->run();

        return $next($request);
    }
}
