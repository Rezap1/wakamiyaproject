<?php

namespace App\Http\Middleware;

use App\Policies\DirectorAcademicMonitoringPolicy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class DirectorAcademicMonitoringAccess
{
    public function __construct(private readonly DirectorAcademicMonitoringPolicy $policy) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user && $this->policy->view($user), 403, 'Monitoring Akademik hanya tersedia untuk Direktur aktif.');

        return $next($request);
    }
}
