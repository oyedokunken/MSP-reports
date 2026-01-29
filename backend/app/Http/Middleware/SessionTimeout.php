<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Carbon\Carbon;

/**
 * Session Timeout Middleware
 * 
 * Enforces maximum session duration based on company settings.
 * Default: 60 minutes of inactivity or 8 hours maximum session.
 */
class SessionTimeout
{
    /**
     * Default session timeout in minutes (if not configured in company settings)
     */
    protected const DEFAULT_TIMEOUT_MINUTES = 60;
    
    /**
     * Maximum session duration in hours (absolute limit)
     */
    protected const MAX_SESSION_HOURS = 8;

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        if (!$user) {
            return $next($request);
        }

        // Check if token has expired based on creation time
        $token = $user->currentAccessToken();
        
        if ($token->created_at) {
            $tokenAge = Carbon::parse($token->created_at);
            $maxSessionDuration = self::MAX_SESSION_HOURS;
            
            // Check absolute session limit (8 hours by default)
            if ($tokenAge->diffInHours(now()) >= $maxSessionDuration) {
                // Revoke the token
                $token->delete();
                
                return response()->json([
                    'status' => 'error',
                    'message' => 'Session has expired due to maximum duration limit. Please log in again.',
                    'code' => 'SESSION_EXPIRED',
                ], 401);
            }
        }

        // Get company-specific timeout setting
        $company = $user->company;
        $timeoutMinutes = self::DEFAULT_TIMEOUT_MINUTES;
        
        if (isset($company->settings['authentication']['session_timeout_minutes'])) {
            $timeoutMinutes = (int) $company->settings['authentication']['session_timeout_minutes'];
            // Ensure timeout is reasonable (between 5 minutes and 480 minutes / 8 hours)
            $timeoutMinutes = max(5, min(480, $timeoutMinutes));
        }

        // Check last activity (stored in token's last_used_at)
        if ($token->last_used_at) {
            $lastActivity = Carbon::parse($token->last_used_at);
            
            if ($lastActivity->diffInMinutes(now()) >= $timeoutMinutes) {
                // Revoke the token
                $token->delete();
                
                return response()->json([
                    'status' => 'error',
                    'message' => 'Session has expired due to inactivity. Please log in again.',
                    'code' => 'SESSION_TIMEOUT',
                ], 401);
            }
        }

        return $next($request);
    }
}
