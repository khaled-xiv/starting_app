<?php

namespace App\Http\Middleware;

use Closure;
use Exception;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Http\Middleware\BaseMiddleware;

class JwtMiddleware extends BaseMiddleware
{

    public function handle($request, Closure $next)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (Exception $e) {
            if ($e instanceof TokenInvalidException){
                $status     = 401;
                $message    = 'This token is invalid. Please Login';
                return response()->json(compact('status','message'),401);
            }else if ($e instanceof TokenExpiredException){
                try
                {
                    $refreshed = JWTAuth::refresh(JWTAuth::getToken());
                    $user = JWTAuth::setToken($refreshed)->toUser();
                    $request->headers->set('Authorization','Bearer '.$refreshed);
                }catch (JWTException $e){
                    return response()->json([
                        'code'   => 103,
                        'message' => 'Token cannot be refreshed, please Login again'
                    ]);
                }
            }else{
                $message = 'Authorization Token not found';
                return response()->json(compact('message'), 401);
            }
        }
        $token=$request->header('Authorization');
        $response = $next($request);
        $response->header('Authorization',$token);
        //add more headers here
        return $response;
    }
}
