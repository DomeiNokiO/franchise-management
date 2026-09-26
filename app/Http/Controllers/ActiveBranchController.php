<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class ActiveBranchController extends Controller
{
    /**
     * Menyimpan cabang aktif di session (untuk pengguna multi-cabang).
     * Middleware branch.access sudah memvalidasi membership di sini.
     */
    public function store(Request $request): RedirectResponse
    {
        Session::put('active_branch_id', (int) $request->integer('branch_id'));

        return back();
    }
}
