<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProtectMassAssignment
{
    /**
     * Routes that are allowed to submit protected fields.
     * Use route names or URI patterns.
     */
    protected array $exemptedRoutes = [
        'payments.verify',
        'invoices.update',
        'approvals.update',
        'workflow.*',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip protection for exempted routes
        if ($this->isExempted($request)) {
            return $next($request);
        }

        // Protected fields that should never be mass assigned by a user request
        $protectedFields = [
            'Status',
            'Approval_Status',
            'Workflow_Status',
            'Created_By',
            'Updated_By',
            'Approved_By',
            'Verified_By',
            'Paid_By',
            'Generated_By'
        ];

        // Remove these fields from the request if they exist
        foreach ($protectedFields as $field) {
            if ($request->has($field)) {
                $request->request->remove($field);
            }
        }

        return $next($request);
    }

    /**
     * Check if the current route is exempted from protection.
     */
    protected function isExempted(Request $request): bool
    {
        $routeName = $request->route()?->getName();

        if (!$routeName) {
            return false;
        }

        foreach ($this->exemptedRoutes as $pattern) {
            if ($pattern === $routeName) {
                return true;
            }

            // Support wildcard patterns like 'workflow.*'
            if (str_contains($pattern, '*') && fnmatch($pattern, $routeName)) {
                return true;
            }
        }

        return false;
    }
}
