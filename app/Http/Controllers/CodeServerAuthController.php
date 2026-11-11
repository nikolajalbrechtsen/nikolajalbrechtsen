<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Nginx calls this (auth_request) before every request to /code/.
 * 204 = let the request through to code-server, 401 = send to login.
 */
class CodeServerAuthController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        $allowed = $user && $user->is_active && $user->can('use_code_editor');

        return response('', $allowed ? 204 : 401);
    }
}
