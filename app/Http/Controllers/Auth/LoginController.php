<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use GuzzleHttp\Client;
use App\User;
use App\Profile;

use Socialite;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    public function login(Request $request)
    {
        $this->validate($request, [
            'email' => 'required|email|exists:users,email',
            'password' => 'required|min:6'
        ], [
            'email.exists' => 'The user credentials were incorrect.'
        ]);

        try {

            $http = new Client;
            // $headers = ['Accept' => 'application/json'];
            $response = $http->post(env('APP_URL') . '/oauth/token', [
                // 'headers' => $headers,
                'verify' => false,
                'form_params' => [
                    'grant_type' => 'password',
                    'client_id' => env('PASSWORD_CLIENT_ID'),
                    'client_secret' => env('PASSWORD_CLIENT_SECRET'),
                    'username' => $request->get('email'),
                    'password' => $request->get('password'),
                    // 'remember' => $request->get('remember'),
                    'remember' => false,
                    'scope' => '',
                ],
            ]);

            $user = User::where('email', $request->get('email'))->first();

            return response()->json([
              'user' => $user,
              'token'=> json_decode((string) $response->getBody(), true)['access_token'],
            ], 200);


            // return json_decode((string) $response->getBody(), true);
            // return $response;

        } catch (\GuzzleHttp\Exception\ClientException $e) {
            return response()->json([
                'error' => 'invalid_credentials',
                'message' => json_decode((string) $e->getResponse()->getBody()->getContents(), true)
            ], 401);
        }
    }

    public function logout(Request $request)
    {
        $accessToken = $request->user()->token();

        DB::table('oauth_refresh_tokens')
            ->where('access_token_id', $accessToken->id)
            ->update([
                'revoked' => true
            ]);

        $accessToken->revoke();

        return response()->json(['message' => '`I guess I\'m kinda hoping you\'ll come back over the rail and get me off the hook here.` - Titanic, Jack, Leonardo DiCarpio'], 201);
    }

    public function socialLogin($social)
    {
        if ($social == "facebook" || $social == "google" || $social == "linkedin" || $social == "graph") {

            $scopes = [];

            if ($social == "graph") {
                $scopes = ['User.Read.All', 'Calendars.Read', 'Mail.Read'];
            }

            return Socialite::with($social)
                            ->scopes($scopes)
                            ->stateless()
                            ->redirect();
        } else {
            return Socialite::with($social)->redirect();
        }
    }

    public function handleProviderCallback($social)
    {
        if ($social == "facebook" || $social == "google" || $social == "linkedin" || $social == "graph") {
            $userSocial = Socialite::with($social)->stateless()->user();
        } else {
            $userSocial = Socialite::with($social)->user();
        }

        $token = $userSocial->token;

        $user = User::firstOrNew(['email' => $userSocial->getEmail()]);

        if (!$user->id) {
            $user->fill([
                "name" => $userSocial->getName(),
                "password"=>bcrypt(str_random(6))
            ]);

            // Save user social
            if ($user->save()) {

                // new profile instance
                $profile = new Profile([
                    'about' => ''
                ]);

                // save profile of user
                $user->profile()
                    ->save($profile);

                $user->assignRole('member');
                // return $user;
            }
        }

        // $user_details = User::find($user->id);

        $access_token = $user->createToken($token)->accessToken;

        return response()->json([
            'user'  => $user,
            'userSocial'  => $userSocial,
            'token' => $access_token,
        ],200);
    }

}
