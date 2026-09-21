<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBranchAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user) {
            abort(401);
        }

        if ($user->isCentralOwner()) {
            return $next($request);
        }

        $branchId = $request->route('branch')?->id
            ?? $request->integer('branch_id')
            ?? $request->session()->get('active_branch_id');

        if (!$branchId || !$user->branches()->whereKey($branchId)->exists()) {
            abort(403, 'Akses cabang tidak diizinkan.');
        }

        return $next($request);
    }
}
