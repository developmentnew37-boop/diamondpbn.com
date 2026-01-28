<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Admin;
use App\Models\AdminOtp;

class AdminOtpController extends Controller
{
    //
    public function show()
    {
        return view('admin.Auth.verifyotp');
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'otp' => 'required|numeric'
        ]);

        $adminId = session('admin_otp_admin_id');
        if (! $adminId) {
            return redirect()->route('admin.login')->with('cus__error', 'Session expired, login again.');
        }

        $admin = Admin::find($adminId);
        $record = AdminOtp::where('admin_id', $adminId)->first();

        if (! $record) {
            return back()->with('cus__error', 'OTP not found');
        }

        if ($record->expires_at < now()) {
            return redirect()->route('admin.login')->with('cus__error', 'OTP expired');
        }

        if ($record->otp != $request->otp) {
            return back()->with('cus__error', 'Invalid OTP');
        }

        // Remember Me
        $remember = session('admin_remember') ? true : false;

        // Login admin via guard
        Auth::guard('admin')->login($admin, $remember);

        // Cleanup
        $record->delete();
        session()->forget(['admin_remember', 'admin_otp_admin_id']);

        return redirect()->route('admin.dashboard');
    }
}
