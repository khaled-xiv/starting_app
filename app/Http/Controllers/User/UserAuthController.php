<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Jobs\SendEmail;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class UserAuthController extends Controller

{
    public function __construct()
    {
        $this->middleware('jwt:user', [
            'except' => ['login','signUp','verifyAccount','recover','check','reset']
        ]);
    }

    public function login(Request $request)
    {
        $rules = [
            'email' => 'required|email',
            'password' => 'required',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()){
            return response()->json([
                'message'=> $validator->errors(),
                'success' => false
            ],400);
        };

        $credentials = $request->only('email', 'password');

        if ($token = $this->guard()->attempt($credentials)) {
            return $this->respondWithToken($token);
        }

        return response()->json(['error' => 'Email or password doesn\'t exist'], 401);

    }

    public function signUp(Request $request)
    {
        $rules = [
            'email' => 'required|email|unique:users',
            'name' => 'required|string',
            'password' => 'required|min:8',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()){
            return response()->json([
                'message'=> $validator->errors(),
                'success' => false
            ],400);
        };
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);
        $this->sendVerificationMail($user->email ,$user->name);

        return $this->login($request);
    }

    public function me(Request $request)
    {
        return response()->json(["user"=>$this->guard()->user()],200);
    }

    public function logout()
    {
        $this->guard()->logout();
        return response()->json(['message' => 'Successfully logged out']);
    }

    public function verifyAccount($verification_code){
        try {
            $check = DB::table('user_verifications')->where('token',$verification_code)->first();
            if(!is_null($check)){
                $user = User::find($check->user_id);
                if(!is_null($user->email_verified_at)){
                    return response()->json([
                        'success'=> true,
                        'message'=> 'Account already verified..'
                    ]);
                }
                $user->update(['email_verified_at' => Carbon::now()]);
                DB::table('user_verifications')->where('user_id',$user->id)->delete();
                return response()->redirectTo('https://shop.ebabashop.com')->with([
                    'success'=> true,
                    'message'=> 'You have successfully verified your email address.'
                ]);
            }
            return response()->json([
                'success'=> false,
                'message'=> "Verification code is invalid."
            ],400);
        }catch (\Exception $e){
            return response()->json([
                'success'=> false,
                'message'=> "An error occurred please try again later"
            ],404);
        }

    }

    public function recover(Request $request)
    {
        $rules = ['email' => 'required|email'];
        $Validator = Validator::make($request->all(), $rules);
        if ($Validator->fails()){
            return response()->json(['error' => $Validator->errors()] , 400);
        }
        $user = User::where('email', $request->email)->first();
        if (is_null($user)) {
            return response()->json(['success' => false, 'error' => ['email'=> "Your email address was not found."]], 403);
        }
        try {
            $this->sendResetLink($user->email,$user->name);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 403);
        }
        return response()->json([
            'success' => true, 'data'=> ['message'=> 'A reset email has been sent! Please check your email.']
        ]);
    }

    public function check(Request $request)
    {
        $rules = ['token' => 'required',];
        $Validator = Validator::make($request->all(), $rules);
        if ($Validator->fails()){
            return response()->json(['error' => $Validator->errors()] , 400);
        }
        $check = DB::table('password_resets')
            ->where(['token'=>$request->token])->first();
        if(!is_null($check)){
            return response()->json([
                'success'=> true,
                'status'=> 'success'
            ],200);
        }
        return response()->json(['success'=> false, 'error'=> "Verification code does not match."],403);
    }

    public function reset(Request $request)
    {
        $rules = [
            'token' => 'required',
            'password' => 'required|min:8',
        ];
        $Validator = Validator::make($request->all(), $rules);
        if ($Validator->fails()){
            return response()->json(['error' => $Validator->errors()] , 400);
        }
        $token = $request->token;
        $check = DB::table('password_resets')
            ->where('token',$token)->first();

        if(!is_null($check)){
            $user = User::where('email',$check->email);
            $user->update(['password'=>Hash::make($request->get('password'))]);
            try {
                DB::table('password_resets')->where('email',$check->email)->delete();
                return response()->json([
                    'success'=> true,
                    'error'=> "You have successfully changed your password"],201);
            }catch (\Exception $e){
            }
        }
        return response()->json(['success'=> false, 'error'=> "Password reset link expired."],403);
    }

    protected function respondWithToken($token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => $this->guard()->factory()->getTTL() * 60
        ]);
    }

    public function guard()
    {
        return Auth::guard('user');
    }

    //
    public function sendVerificationMail($email, $name){
        do {
            $token = Str::random(30);
        } while ( DB::table('user_verifications')->where( 'token', $token)->exists() );

        $user_id=User::where('email',$email)->first()->id;
        DB::table('user_verifications')
            ->insert([
                'user_id'=> $user_id,
                'token'=>$token,
                'created_at'=>Carbon::now(),
            ]);
        $url = URL::to('/').'/api/auth/user/verifyuser';
        $subject = "Please verify your email address.";
        $credentials=(object)[
          'name'=>$name,
          'email'=>$email,
          'url'=>$url,
          'token'=>$token,
          'subject'=>$subject,
        ];
        SendEmail::dispatch($credentials)
            ->delay(Carbon::now()->addSeconds(60));
    }

    public function sendResetLink($email, $name){
        do {
            $token = Str::random(30);
        } while ( DB::table('password_resets')->where( 'token', $token)->exists() );

        $user_id=User::where('email',$email)->first()->id;
        DB::table('password_resets')
            ->insert([
                'email'=> $email,
                'token'=>$token,
                'created_at'=>Carbon::now(),
            ]);
        $url = URL::to('/').'/api/auth/user/verifyuser';
        $subject = "Reset Password Notification";
        $credentials=(object)[
            'name'=>$name,
            'email'=>$email,
            'url'=>$url,
            'token'=>$token,
            'subject'=>$subject,
        ];
        SendEmail::dispatch($credentials)
            ->delay(Carbon::now()->addSeconds(60));
    }
}
