<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DenyCentralFinancialAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isCentralOwner()) {
            abort(403, 'Laporan keuangan cabang hanya tersedia untuk Owner Mitra.');
        }

        return $next($request);
    }
}
