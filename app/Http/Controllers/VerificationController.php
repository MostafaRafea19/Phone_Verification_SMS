<?php

namespace App\Http\Controllers;

use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PeterPetrus\Auth\PassportToken;

class VerificationController extends Controller
{
    public function Check(Request $request)
    {
        $data = $request->validate([
            'phone' => 'required'
        ], [
            'phone.required' => 'Phone number is required'
        ]);

        if ($data['phone'][0] === '0') {
            $mobile = "+2" . $data['phone'];
        }

        $user = DB::table('users')->where('phone', '=', $mobile)->first();

        if ($user) {
            $MessageBird = new \MessageBird\Client('J0LGef06gzcC3OojLmsySKCc9');
            $Message = new \MessageBird\Objects\Message();

            $Message->originator = 'Takwen';

            $Message->recipients = array($mobile);

            $code = rand(100000, 999999);
            $Message->body = 'Your account verification code is ' . $code;
            $MessageBird->messages->create($Message);

            $_user = User::find($user->id);
            $_user->update([
                'verification_code' => $code,
            ]);
            $token = $_user->createToken('Token')->accessToken;
            return response()->json($token);

        } else {
            return response()->json('There is no user registered with that phone number');
        }
    }

    public function verify(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|digits:6'
        ], [
            'code.required' => 'Code is required',
            'code.digits' => 'Code must be exactly 6 digits'
        ]);

        $token = PassportToken::dirtyDecode($request->header('authorization'));
        if ($token['valid']) {
            $token_exists = PassportToken::existsValidToken(
                $token['token_id'],
                $token['user_id']
            );

            if ($token_exists) {
                $user = User::find($token['user_id']);
                if ($user->verification_code == $data['code']) {
                    $user->update([
                        'is_verified' => 1
                    ]);
                    $user_data = DB::table('users')->where('id', '=', $token['user_id'])->first();
                    return response()->json($user_data, 200);
                } else {
                    return response()->json('Verification Code mismatch');
                }
            } else {
                return response()->json('User does\'t exist');
            }
        } else {
            return response()->json('Token is invalid');
        }
    }
}
