<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AdminOtp;
use App\Models\Admin;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class AdminAuthenticatorController extends Controller
{
    //
    public function show()
    {
        return view('admin.Auth.login');
    }

    // STEP 1 — Email + Password login → generate OTP
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        // Normalize email (avoid trailing spaces issues)
        $email = trim($request->email);

        // 1️⃣ Check if admin exists
        $admin = Admin::where('email', $email)->first();

        if (!$admin) {
            return back()
                ->with('cus__error', 'Email does not match any admin account.')
                ->withInput();
        }

        // 2️⃣ Email exists → now check password
        if (! Hash::check($request->password, $admin->password)) {
            return back()
                ->with('cus__error', 'Password is incorrect.')
                ->withInput();
        }

        // 3️⃣ If both email + password correct → continue login flow

        // Remember me
        session(['admin_remember' => $request->has('remember')]);

        // Generate OTP
        $otp = rand(100000, 999999);

        AdminOtp::updateOrCreate(
            ['admin_id' => $admin->id],
            [
                'otp'        => $otp,
                'expires_at' => now()->addMinutes(10)
            ]
        );

        // Send OTP email
        Mail::raw("Your Admin OTP Code: $otp", function ($message) use ($admin) {
            $message->to($admin->email)->subject('Admin Login OTP');
        });

        // Store admin ID for OTP verification
        session(['admin_otp_admin_id' => $admin->id]);

        return redirect()->route('admin.otp')
            ->with('otp_sent', 'OTP sent to your email.');
    }
    public function logout(Request $request)
    {
        // Logout admin from guard
        Auth::guard('admin')->logout();

        // Invalidate session
        $request->session()->invalidate();

        // Regenerate CSRF token
        $request->session()->regenerateToken();

        // Redirect to login
        return redirect()->route('admin.login')->with('cus__success', 'Logged out successfully.');
    }
}
