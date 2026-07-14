<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        $allowed = array_map(
            fn (string $role) => UserRole::from($role),
            $roles
        );

        if (! $user->hasRole(...$allowed)) {
            abort(403, 'You do not have permission to access this area.');
        }

        return $next($request);
    }
}
