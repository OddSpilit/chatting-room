<?php

namespace App\Http\Controllers;

use App\LoginUser;
use App\Message;
use App\User;
use Illuminate\Http\Request;
use GatewayClient\Gateway;
use Auth;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {

        $this->middleware('auth');

        Gateway::$registerAddress = '127.0.0.1:1238';
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index(Request $request)
    {
//        $this->usersByGroup($request->client_id, Auth::id());die;
//        $room_id = $request->input('room_id') ? $request->input('room_id') : 1;
//        session()->put('room_id', $room_id);

        return view('home');
    }

    public function init(Request $request)
    {
        $this->bind($request);
        $this->users();
        $this->history();
        $this->login($request);
        $this->ping();
    }

    //绑定前端传过来的client_id和user_id
    private function bind($req)
    {
        $user = User::where(['id'=>Auth::id()])->get();
        $id = Auth::id();
        $client_id = $req->client_id;
        Gateway::bindUid($client_id, $id);
//        //当前用户进入一个房间
//        Gateway::joinGroup($client_id, session('room_id'));
//        Gateway::setSession($client_id, [
//            'id' => $user[0]['id'],
//            'avatar' => Auth::user()->avatar(Auth::id()),
//            'name' => $user[0]['name'],
//        ]);
    }

    //user就是当前登录用户的对象模型
    public function login($request)
    {
        $user = User::where(['id'=>Auth::id()])->get();
//        $res = LoginUser::where('name', '=',$user[0]['name'])->get();
//        $res = json_decode($res);
//        if (!($res)){
//            LoginUser::query()->insert([
//                'user_id' => Auth::id(),
//                'avatar' => Auth::user()->avatar(Auth::id()),
//                'name' => $user[0]['name'],
//                'client_id'=>$request->client_id,
//            ]);
//        }else {
////            $get = LoginUser::query()->orderBy('id', 'desc')->first();//获取最后一条的id
//            LoginUser::query()->where(['user_id'=>Auth::id()])->delete();
//
//        }
        LoginUser::where('name', '=',$user[0]['name'])->delete();
        LoginUser::query()->insert([
                'user_id' => Auth::id(),
                'avatar' => Auth::user()->avatar(Auth::id()),
                'name' => $user[0]['name'],
                'client_id'=>$request->client_id,
            ]);
        $data = [
            'type' => 'login',
            'who' => [
                'avatar' => Auth::user()->avatar(Auth::id()),
                'name' => $user[0]['name'],
                'content' => '进入了这个聊天室',
                'time' => date("Y-m-d H:i:s", time())
            ],
            'data'=> LoginUser::all(),
        ];
        $this->users();
//
        Gateway::sendToAll(json_encode($data));
    }

    public function say(Request $request)
    {
        $user = User::where(['id'=>Auth::id()])->get();
        $data = [
            'type' => 'say',
            'data' => [
                'avatar' => Auth::user()->avatar(Auth::id()),
                'name' => $user[0]['name'],
                'content' => $request->input('content'),
                'time' => date("Y-m-d H:i:s", time()),
            ],
        ];

        //私聊
        if ($request->input('user_id')) {
            $name = LoginUser::query()->where('id','=',$request->input('user_id'))->value('name');
            $user_id = LoginUser::query()->where('id','=',$request->input('user_id'))->value('user_id');
            $data['data']['name'] = $user[0]['name'] . '对' .$name. '说:';
            Gateway::sendToUid(Auth::id(), json_encode($data));

            $data['data']['name'] = $user[0]['name'] . '对你说:';
            Gateway::sendToUid($user_id, json_encode($data));

            return;
        }

        Gateway::sendToAll(json_encode($data));

        Message::query()->create([
            'user_id' => Auth::id(),
//            'room_id' => session('room_id'),
            'content' => $request->input('content'),
        ]);
    }

    //测试websocket连接
    public function ping()
    {
        $data = [
            'type' => 'ping',
        ];

        Gateway::sendToAll(json_encode($data));
    }

    public function history()
    {
        $data = [
            'type' => 'history',
        ];

        //预加载
        $messages = Message::query()->with(['user'])->where(['user_id'=>Auth::id()])->orderBy('id', 'desc')->limit(5)->get();
        //->where('room_id', session('room_id'))

        //map重新组装数据返回
        $data['data'] = $messages->map(function ($item, $key) {
            return [
                'avatar' => $item->user->avatar(Auth::id()),
                'name' => $item->user->name,
                'content' => $item->content,
                'time' => $item->created_at->format("Y-m-d H:i:s"),
            ];
        });

        Gateway::sendToUid(Auth::id(),json_encode($data));
    }

    public function users()
    {
        $session = Gateway::getAllClientSessions();

//        $value = [];
//
//        foreach ($session as $s) {
//            $value[] = $s;
//        }
//
//        //针对同一浏览器登录出现多个相同用户
//        $users = array_unique($value, SORT_REGULAR);

//        $data = [
//            'type' => 'users',
//            'data' => $session,
//        ];
//
//        Gateway::sendToAll(json_encode($data));

        //获取当前房间在线的用户
//        $uids = Gateway::getUidListByGroup(session('room_id'));
//
//        $users = [];
//
//        foreach ($uids as $uid) {
//            $user = User::query()->find($uid);
//            $user['avatar'] = $user->avatar($uid);
//            $users[] = $user;
//        }

        $session_data = [
            'type' => 'users',
            'data' => LoginUser::all(),
        ];

        Gateway::sendToAll(json_encode($session_data));
    }

    public function usersByGroup()
    {
//        $uids = Gateway::getUidListByGroup(session('room_id'));
//        $users = [];
//
//        foreach ($uids as $uid) {
//            $user = User::query()->find($uid);
//            $user['avatar'] = $user->avatar();
//            $users[] = $user;
//        }
//
//        $data = [
//            'type' => 'users',
//            'data' => $users,
//        ];

        $get = LoginUser::query()->orderBy('id', 'desc')->first();//获取最后一条的id
        $res = LoginUser::query()->where('id')->update($get['id']+1);

        dd($res);
    }

    public function del(Request $request){
        $user = LoginUser::where(['client_id'=>$request->client_id])->get();
        $data = [
            'type' => 'after',
            'who' => [
                'avatar' => Auth::user()->avatar(Auth::id()),
                'name' => $user[0]['name'],
                'content' => '离开了聊天室',
                'time' => date("Y-m-d H:i:s", time())
            ],
        ];
        LoginUser::where(['client_id'=>$request->client_id])->delete();
        $this->users();
        Gateway::sendToAll(json_encode($data));
    }

}
