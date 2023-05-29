<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\Hospital;
use App\Models\Session;
use App\Models\Step;
use App\Models\User;
use App\Models\UserSession;
use App\Models\UserStep;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use stdClass;

class SessionController extends Controller
{
    public function __construct()
    {
        $this->middleware('jwt.verify');
    }

    public function sessions()
    {
        $user = Auth::user();

        if (!$user || $user->status != 1 || $user->user_type == 1) {
            return response()->json(['msg' => 'Invalid user', 'status' => false], 200);
        }

        $userSessions = UserSession::where(['user_id' => $user->id])->get();
        foreach ($userSessions as $userSession) {
            $session = Session::where(['id' => $userSession->session_id])->first();
            if ($session) {
                $userSession->name = $session->name;
                $userSession->description = $session->description;
                $userSession->order = $session->order;
                $userSession->thumbnail = $session->thumbnail;
                unset($userSession->session_id);
            }
        }

        $sessions = $userSessions->sort(function ($first, $second) {
            return $first->order > $second->order;
        });

        return response()->json(['msg' => 'Sessions fetched', 'data' => $sessions, 'status' => true], 200);
    }

    public function steps(Request $request)
    {
        $user = Auth::user();
        $validator = Validator::make($request->all(), [
            'session_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['msg' => $validator->errors()->first(), 'status' => false], 200);
        }



        if (!$user || $user->user_type == 1 || $user->status != 1) {
            return response()->json(['msg' => 'Invalid user', 'status' => false], 200);
        }

        $userSteps = UserStep::where(['user_id' => $user->id, 'session_id' => $request->session_id])->get();
        foreach ($userSteps as $userStep) {
            $session = Step::where(['id' => $userStep->step_id])->first();
            if ($session) {
                $userStep->name = $session->name;
                $userStep->description = $session->description;
                $userStep->order = $session->order;
                $userStep->url = $session->url;
                $userStep->thumbnail = $session->thumbnail;
                unset($userStep->step_id);
            }
        }

        $steps = $userSteps->sort(function ($first, $second) {
            return $first->order > $second->order;
        });

        return response()->json(['msg' => 'Steps fetched', 'data' => $steps, 'status' => true], 200);
    }

    public function account()
    {
        $user = Auth::user();
        if (!$user->status) {
            return response()->json(['msg' => 'Invalid user', 'status' => false], 200);
        } else {
            if ($user->user_type === 3 || $user->user_type === 2) {
                $hospital = Hospital::where('id', $user->hospital_id)->first();
                unset($hospital->id);
                unset($hospital->region_id);
                unset($hospital->status);
                $user->hospital = $hospital;
            }
            unset($user->hospital_id);
            unset($user->region_id);
            unset($user->status);

            if ($user->user_type != 1) {
                $user->watch_time = UserStep::where('user_id', $user->id)->sum('watch_time');
            }
            return response()->json(['msg' => 'Steps fetched', 'data' => $user, 'status' => true], 200);
        }
    }
}
