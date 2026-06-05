<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;

class AuditActivity
{
    public function handle(Request $request, Closure $next)
    {
        return $next($request);
    }
}
