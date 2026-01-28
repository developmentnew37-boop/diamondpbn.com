<?php

namespace App\Http\Controllers\Admin;

use App\Models\Admin;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AdminPasswordReset;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class AdminPasswordResetController extends Controller
{
    //
    public function show()
    {
        return view('admin.Auth.forgotpassword');
    }

    public function sendResetLink(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:admins,email'
        ]);

        // Delete existing tokens for this email
        AdminPasswordReset::where('email', $request->email)->delete();

        // Create new token
        $token = Str::random(64);

        AdminPasswordReset::create([
            'email' => $request->email,
            'token' => $token,
            'created_at' => now()
        ]);

        // Send reset email
        Mail::send('admin.Auth.email-reset', ['token' => $token], function ($m) use ($request) {
            $m->to($request->email)->subject('Admin Password Reset');
        });

        return back()->with('cus__success', 'Reset link sent to your email.');
    }
    public function showResetForm($token)
    {

        $record = AdminPasswordReset::where('token', $token)
            ->where('created_at', '>', now()->subHour())
            ->first();

        if (!$record) {
            return redirect()->route('admin.forgot')
                ->with('cus__error', 'Invalid or expired reset link.');
        }

        return view('admin.Auth.reset', [
            'token' => $token,
            'email' => $record->email,
        ]);
    }


    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:admins,email',
            'password' => 'required|min:6|confirmed',
            'token' => 'required'
        ]);

        $record = AdminPasswordReset::where('email', $request->email)
            ->where('token', $request->token)
            ->where('created_at', '>', now()->subHours()) // 1 hour expiry
            ->first();

        if (!$record) {
            return back()->with('cus__error', 'Invalid or expired reset link.');
        }

        // Update password
        $admin = Admin::where('email', $request->email)->first();
        $admin->update([
            'password' => Hash::make($request->password)
        ]);

        // Delete reset record
        $record->delete();

        return redirect()->route('admin.login')->with('cus__success', 'Password reset successfully.');
    }
}
