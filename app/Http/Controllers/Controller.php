<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Return a Blade view for browser requests and the same view data as
     * JSON for SPA/API clients. Used by GET endpoints exposed to the Vue SPA.
     */
    protected function viewOrJson(Request $request, string $view, array $data = [])
    {
        if ($request->expectsJson()) {
            return response()->json($data);
        }

        return view($view, $data);
    }
}