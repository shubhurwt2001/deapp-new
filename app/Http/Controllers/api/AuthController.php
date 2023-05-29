<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\Hospital;
use App\Models\Region;
use App\Models\Session;
use App\Models\Step;
use App\Models\User;
use App\Models\UserSession;
use App\Models\UserStep;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function regions()
    {
        $regions = Region::where('status', 1)->get();
        return response()->json(['msg' => 'Regions fetched', 'data' => $regions, 'status' => true], 200);
    }

    public function hospitals(Request $request)
    {
        if ($request->region_id) {
            $hospitals = Hospital::where(['region_id' => $request->region_id, 'status' => 1])->get();
        } else {
            $hospitals = Hospital::where(['status' => 1])->get();
        }
        return response()->json(['msg' => 'Hospitals fetched', 'data' => $hospitals, 'status' => true], 200);
    }
    public function login(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'deapp_id' => 'required',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['msg' => $validator->errors()->first(), 'status' => false], 200);
        }

        $user = User::where('deapp_id', $request->deapp_id)->first();
        if (!$user) {
            $user = User::where('email', $request->deapp_id)->first();
        }
        if (!$user) {
            return response()->json(['msg' => 'Unauthorized user', 'status' => false], 200);
        }

        if (!$token = auth()->attempt(['email' => $user->email, 'password' => $request->password])) {
            return response()->json(['msg' => 'Unauthorized user', 'status' => false], 200);
        }
        return $this->createNewToken($token);
    }
    /**
     * Register a User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function licenseKey(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'license_key' => 'required',
            'hospital_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['msg' => $validator->errors()->first(), 'status' => false], 200);
        }

        $valid = User::where(['user_type' => 2, 'license_key' => $request->license_key, 'hospital_id' => $request->hospital_id, 'status' => 1])->first();

        if (!$valid) {
            return response()->json(['msg' => 'Invalid license key', 'status' => false], 200);
        } else {
            return response()->json(['msg' => 'Valid license key', 'status' => true], 200);
        }
    }
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|between:2,100',
            'email' => 'required|string|email|max:100|unique:users',
            'password' => 'required|string|min:6',
            'user_type' => 'required'
        ]);
        if ($validator->fails()) {
            return response()->json(['msg' => $validator->errors()->first(), 'status' => false], 200);
        }
        if ($request->user_type != 3 && $request->user_type != 4) {
            return response()->json(['msg' => 'Invalid user type', 'status' => false], 200);
        }


        if ($request->user_type == 3) {
            $validator = Validator::make($request->all(), [
                'license_key' => 'required',
                'hospital_id' => 'required',
                'region_id' => 'required'
            ]);

            if ($validator->fails()) {
                return response()->json(['msg' => $validator->errors()->first(), 'status' => false], 200);
            }

            $valid = User::where(['user_type' => 2, 'license_key' => $request->license_key, 'hospital_id' => $request->hospital_id, 'status' => 1])->first();

            if (!$valid) {
                return response()->json(['msg' => 'Invalid license key', 'status' => false], 200);
            }
        }
        $user = new User();
        $user->name = $request->name;
        $user->deapp_id = 'DEAPP' . count(User::all()) . chr(rand(65, 90)) . rand(100, 999);
        $user->password = Hash::make($request->password);
        $user->user_type = $request->user_type;
        $user->license_key = $request->license_key;
        $user->email = $request->email;
        $user->region_id = $request->region_id;
        $user->hospital_id = $request->hospital_id;
        $user->save();

        $session = Session::where(['order' => 1, 'status' => 1])->first();
        if ($session) {
            $steps = Step::where(['status' => 1, 'session_id' => $session->id])->orderBy('order', 'ASC')->get();

            $userSession = new UserSession();
            $userSession->user_id = $user->id;
            $userSession->session_id = $session->id;
            $userSession->save();

            foreach ($steps as $step) {
                $userStep = new UserStep();
                $userStep->session_id = $session->id;
                $userStep->step_id = $step->id;
                $userStep->user_id = $user->id;
                $userStep->save();
            }
        }
        return response()->json([
            'msg' => 'User successfully registered',
            'data' => $user, 'status' => true
        ], 200);
    }

    public function forget(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['msg' => $validator->errors()->first(), 'status' => false], 200);
        }

        if ($request->type == 'password') {

            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'deapp_id' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json(['msg' => $validator->errors()->first(), 'status' => false], 200);
            }

            $user = User::where(['email' => $request->email, 'deapp_id' => $request->deapp_id])->first();
            if (!$user) {
                return response()->json(['msg' => 'Invalid Email or DeappId', 'status' => false], 200);
            } else {
                return response()->json(['msg' => 'Temporary password sent to registered email', 'status' => true], 200);
            }
        } else if ($request->type == 'deapp_id') {

            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
            ]);

            if ($validator->fails()) {
                return response()->json(['msg' => $validator->errors()->first(), 'status' => false], 200);
            }

            $user = User::where(['email' => $request->email, 'deapp_id' => $request->deapp_id])->first();
            if (!$user) {
                return response()->json(['msg' => 'Invalid DeappId', 'status' => false], 200);
            } else {
                return response()->json(['msg' => 'DeappId sent to registered email', 'status' => true], 200);
            }
        } else {
            return response()->json(['msg' => 'Type should be password or deapp_id', 'status' => false], 200);
        }
    }
    protected function createNewToken($token)
    {
        return response()->json([
            'data' => [
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => Auth::factory()->getTTL() * 60,
                'user' => Auth::user()
            ],
            'msg' => 'User loggedin successfully',
            'status' => true
        ], 200);
    }
}
