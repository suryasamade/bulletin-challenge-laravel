<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\Message;
use App\Models\User;
use Error;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class MessageController extends Controller
{
    public function index()
    {
        $messages = Message::latest('id')->with('user')->paginate(10);

        if (!Auth::check()) {
            return view('index', ['messages' => $messages]);
        }

        return view('index', [
            'messages' => $messages,
            'isEmailVerified' => !is_null(Auth::user()->email_verified_at),
            'authUserId' => Auth::user()->id,
            'authUserName' => Auth::user()->name
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'nullable|min:3|max:16',
            'title' => 'required|min:10|max:32',
            'body' => 'required|min:10|max:200',
            'password' => 'nullable|numeric|digits:4',
            'image' => 'nullable|image|mimes:png,jpg,svg,jpeg,gif|max:1024',
        ]);

        $user = User::findOrFail($request->user_id);

        $message = new Message;

        $message->name      = $request->name;
        $message->title     = $request->title;
        $message->body      = $request->body;
        $message->password  = is_null($request->password) ? null : Hash::make($request->password);

        if ($request->hasFile('image')) {
            $imagePath              = $request->file('image')->store('public/images');
            $message->image_name    = explode('/', $imagePath)[2];
        }

        $message->user()->associate($user);

        $message->save();

        return back();
    }

    public function show($id)
    {
        return Message::find($id);
    }

    public function update(Request $request, $id)
    {
        if (!($this->passwordValidation($request) || $this->memberValidation($id))) return new Error("Update data failed!");

        $request->validate([
            'nameEdit' => 'required|min:3|max:16',
            'titleEdit' => 'required|min:10|max:32',
            'bodyEdit' => 'required|min:10|max:200',
            'password' => 'nullable|numeric|digits:4',
            'imageEdit' => 'nullable|image|mimes:png,jpg,svg,jpeg,gif|max:2048',
        ]);

        $messageUpdate = Message::find($id);

        $messageUpdate->name    = $request->nameEdit;
        $messageUpdate->title   = $request->titleEdit;
        $messageUpdate->body    = $request->bodyEdit;

        if (isset($request->deleteImage)) {
            $messageUpdate->image_name = null;

            Storage::delete($request->oldImagePath);

            return $messageUpdate->save();
        }

        if ($request->hasFile('imageEdit')) {
            isset($request->oldImagePath) ? Storage::delete($request->oldImagePath) : null;

            $imagePath                  = $request->file('imageEdit')->store('public/images');

            $messageUpdate->image_name  = explode('/', $imagePath)[2];
        }

        if ($messageUpdate->save() === [] || $messageUpdate->save() === false) {
            return null;
        }

        return true;
    }

    public function destroy(Request $request)
    {
        if (!($this->passwordValidation($request) || $this->memberValidation($request->id))) return false;

        Message::find($request->id)->forceDelete();
        Storage::delete($request->image);

        return back();
    }

    public function passwordValidation(Request $request)
    {
        $hashedPassword = Message::find($request->id)->password;

        return Hash::check($request->password, $hashedPassword);
    }

    protected function memberValidation($id)
    {
        $message = Message::find($id);

        return $message->user_id === $message->user->id;
    }
}
