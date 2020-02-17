<?php

namespace App\Http\Middleware;

use Closure;
use App\User;

class CheckUser
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        /** Validate if is attached the authorization header */
        if (!$request->header('Authorization')) {
            return response()->json([
                'ok' => false,
                'message' => 'It is mandatory to attach the authentication header.'
            ], 401); 
        }
        /** Get the key provided */
        $key = $request->header('Authorization');
        /** Find the user anda validate if is in the db */
        $user = User::where('key', $key)->first();

        if($user == null){
            return response()->json([
                'ok' => false,
                'message' => 'The password provided is not attached to any user.'
            ], 400); 
        }
        
        $request->user_id = $user['id'];
        /** If all is well, we go to the corresponding function */
        return $next($request);
    }
}
