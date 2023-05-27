<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\Session;
use App\Models\Step;
use App\Models\User;
use App\Models\UserSession;
use App\Models\UserStep;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SessionController extends Controller
{
    public function __construct()
    {
        $this->middleware('jwt.verify');
    }

    public function sessions(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['msg' => $validator->errors()->first()], 400);
        }

        $user = User::where(['id' => $request->user_id, 'status' => 1])->first();

        if (!$user || $user->user_type == 1 ||  $user->user_type == 2) {
            return response()->json(['msg' => 'Invalid user'], 400);
        }

        $userSessions = UserSession::where(['user_id' => $request->user_id])->get();
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

        return response()->json(['msg' => 'Sessions fetched', 'data' => $sessions], 200);
    }

    public function steps(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
            'session_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['msg' => $validator->errors()->first()], 400);
        }

        $user = User::where(['id' => $request->user_id, 'status' => 1])->first();

        if (!$user || $user->user_type == 1 ||  $user->user_type == 2) {
            return response()->json(['msg' => 'Invalid user'], 400);
        }

        $userSteps = UserStep::where(['user_id' => $request->user_id, 'session_id' => $request->session_id])->get();
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

        return response()->json(['msg' => 'Steps fetched', 'data' => $steps], 200);
    }
}
