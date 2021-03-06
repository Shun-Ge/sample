<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests;
use App\Models\User;
use Auth;
use Mail;

class UsersController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth', [
            //except 除了此处指定的动作以外，所有其他动作都必须登录用户才能访问
            'except' => ['index', 'show', 'create', 'store', 'confirmEmail']
        ]);

        $this->middleware('guest', [
            'only' => ['create']
        ]);
    }

    public function index()
    {
        //用户首页列表，分页10个
        $users = User::paginate(10);
        return view('users.index', compact('users'));
    }

    public function create()
    {
        return view('users.create');
    }

    public function show(User $user)
    {
        $statuses = $user->statuses()
                         ->orderBy('created_at', 'desc')
                         ->paginate(30);
        return view('users.show', compact('user', 'statuses'));
    }

    //注册用户逻辑
    public function store(Request $request)
    {
        $this->validate($request, [
            'name' => 'required|max:50',
            'email' => 'required|email|unique:users|max:255',
            'password' => 'required|confirmed|min:6'
        ]);

        $user = User::create([
               'name' => $request->name,
               'email' => $request->email,
               'password' => bcrypt($request->password)
        ]);
        // Auth::login($user); //设置注册成功自动登陆
        // session()->flash('success', '欢迎，您将在这里开启一段新的旅程(ง •_•)ง');
        $this->sendEmailConfirmationTo($user);
        session()->flash('success', '验证邮件已经发送到您的注册邮箱，请注意查收。(ง •_•)ง');
        return redirect('/');
    }

    //编辑用户页面，用策略验证本人才可以执行
    public function edit(User $user)
    {
        $this->authorize('update', $user);
        return view('users.edit', compact('user'));
    }

    //更新用户资料
    public function update(User $user, Request $request)
    {
        $this->validate($request, [
            'name' => 'required|max:50',
            'password' => 'nullable|confirmed|min:6'
        ]);

        $this->authorize('update', $user);
        $data = [];
        $data['name'] = $request->name;
        if ($request->password){
            $data['password'] = bcrypt($request->password);
        }
        $user->update($data);

        session()->flash('success', '个人资料更新成功(ง •_•)ง');
        return redirect()->route('users.show', $user->id);
    }

    //删除用户，只有管理员才可以
    public function destroy(User $user)
    {
        $this->authorize('destroy', $user);
        $user->delete();
        session()->flash('success', '成功删除用户！');
        return back();
    }

    //注册成功发送邮件激活
    public function sendEmailConfirmationTo($user)
    {
        $view = 'emails.confirm';
        $data = compact('user');
        $to = $user->email;
        $subject = '感谢注册 Sample 应用！请确认激活您的邮箱！';

        Mail::send($view, $data, function ($message) use ($to, $subject) {
            $message->to($to)->subject($subject);
        });
    }

    //接收token验证，完成激活
    public function confirmEmail($token)
    {
        $user = User::where('activation_token', $token)->firstOrFail();

        $user->activated = true;
        $user->activation_token = null;
        $user->save();

        Auth::login($user);
        session()->flash('success', '恭喜你，激活成功！');
        return redirect()->route('users.show', [$user]);
    }


    //关注人列表
    public function followings(User $user)
    {
        $users = $user->followings()->paginate(30);
        $title = '关注的人';
        return view('users.show_follow', compact('users', 'title'));
    }

    //粉丝列表
    public function followers(User $user)
    {
        $users = $user->followers()->paginate(30);
        $title = '粉丝';
        return view('users.show_follow', compact('users', 'title'));
    }


}
