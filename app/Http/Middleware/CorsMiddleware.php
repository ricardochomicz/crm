<?php

namespace App\Http\Middleware;

use Closure;

class CorsMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if ($request->is('api/*')) {
            header('Access-Control-Allow-Origin', 'http://localhost:4200');
            header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
            header('Access-Control-Allow-Methods', 'GET, POST, PATCH, PUT, DELETE');
            header('Access-Control-Expose-Headers', 'Authorization');
        }
        return $next($request);

//        return $next($request)
//            //Acrescente as 3 linhas abaixo
//            ->header('Access-Control-Allow-Origin', "*")
//            ->header('Access-Control-Allow-Methods', "POST, GET, PUT, PATCH, DELETE, OPTIONS")
//            ->header('Access-Control-Allow-Headers', "Accept, Authorization, Content-Type");
    }
}
