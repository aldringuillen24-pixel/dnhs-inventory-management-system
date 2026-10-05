<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\ViewErrorBag;
use Symfony\Component\HttpFoundation\Response;

/**
 * Convert Blade-style redirects into JSON for SPA/API clients.
 *
 * Applied only to the JSON API route groups in routes/web.php. It is a
 * no-op for standard browser requests (non-JSON) and for non-redirect
 * responses, so existing Blade behavior is untouched.
 *
 * Success flashes become 200 {"status":"success","message":...} and
 * error flashes / error bags become 422 {"status":"error",...}.
 * Validation failures never reach here: Laravel already renders them
 * as 422 JSON when the request expects JSON.
 */
class ConvertRedirectsToJson
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->expectsJson() || ! $response instanceof RedirectResponse) {
            return $response;
        }

        $sessionErrors = $request->hasSession() ? $request->session()->get('errors') : null;
        $errorBag = $sessionErrors instanceof ViewErrorBag ? $sessionErrors->getBag('default') : null;
        $fieldErrors = $errorBag ? $errorBag->messages() : [];

        $errorMessage = $request->hasSession() ? $request->session()->get('error') : null;
        if (! is_string($errorMessage) || $errorMessage === '') {
            $firstBagError = $errorBag ? $errorBag->first() : null;
            $errorMessage = is_string($firstBagError) && $firstBagError !== '' ? $firstBagError : null;
        }

        $successMessage = $request->hasSession() ? $request->session()->get('success') : null;
        if (! is_string($successMessage) || $successMessage === '') {
            $successMessage = null;
        }

        $failed = ! empty($fieldErrors) || $errorMessage !== null;

        $payload = [
            'status' => $failed ? 'error' : 'success',
            'message' => $failed
                ? ($errorMessage ?? 'The action could not be completed.')
                : ($successMessage ?? 'Done.'),
            'redirect' => $response->getTargetUrl(),
        ];

        if (! empty($fieldErrors)) {
            $payload['errors'] = $fieldErrors;
        }

        return response()->json($payload, $failed ? 422 : 200);
    }
}
