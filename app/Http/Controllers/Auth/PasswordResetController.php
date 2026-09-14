<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PasswordResetController extends Controller
{
    public function showForgotPasswordForm(Request $request)
    {
        $token = $request->query('token');
        $email = $request->query('email');

        return view('components.auth.forgot-password-form', [
            'title' => 'Password Reset',
            'token' => $token,
            'email' => $email,
        ]);
    }

    public function sendOtp(Request $request)
    {
        // Validate the email input
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        // Generate a 6-digit OTP
        $otp = random_int(100000, 999999);

        // Hash the OTP for security
        $otpHash = bcrypt($otp);

        // Store the OTP in the database with an expiration time (e.g., 5 minutes)
        \App\Models\PasswordResetOTP::create([
            'email' => $request->email,
            'otp_hash' => $otpHash,
            'expires_at' => now()->addMinutes(5),
            'attempts' => 0,
        ]);

        // Send the OTP to the user's email (you can use Laravel's Mail facade)
        \Illuminate\Support\Facades\Mail::to($request->email)->send(new \App\Mail\PasswordResetOtpMail($otp));

        return redirect()->route('password.otp', ['email' => $request->email])
            ->with('success', 'An OTP has been sent to your email. Please check your inbox.');
    }

    public function showOtpForm(Request $request)
    {
        $email = $request->query('email');
        $otpRecord = \App\Models\PasswordResetOTP::where('email', $email)
            ->whereNull('used_at')
            ->latest('expires_at')
            ->first();

        return view('components.auth.otp-verification-form', [
            'title' => 'Enter OTP',
            'email' => $email,
            'expiresAt' => $otpRecord?->expires_at,
        ]);
    }

    public function verifyOtp(Request $request)
    {
        // Validate the OTP input
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'otp' => 'required|digits:6',
        ]);

        // Retrieve the OTP record from the database
        $otpRecord = \App\Models\PasswordResetOTP::where('email', $request->email)
            ->whereNull('used_at')
            ->where('attempts', '<', 5)
            ->where('expires_at', '>', now())
            ->first();

        if (!$otpRecord) {
            return back()->withErrors(['otp' => 'Invalid or expired OTP.']);
        }

        // Check if the provided OTP matches the hashed OTP
        if (!\Illuminate\Support\Facades\Hash::check($request->otp, $otpRecord->otp_hash)) {
            // Increment the attempts count
            $otpRecord->increment('attempts');

            return back()->withErrors(['otp' => 'Invalid OTP.']);
        }

        // Mark the OTP as used
        $otpRecord->update(['used_at' => now()]);

        $request->session()->put([
            'password_reset_email' => $request->email,
            'password_reset_verified_until' => now()->addMinutes(10),
        ]);

        // The session authorization, not a browser-supplied token, controls the reset.
        return redirect()->route('password.reset.form', [
            'email' => $request->email,
        ]);
    }

    public function showResetForm(Request $request)
    {
        $email = $request->session()->get('password_reset_email');
        $verifiedUntil = $request->session()->get('password_reset_verified_until');

        if (!$email || !$verifiedUntil || now()->greaterThan($verifiedUntil)) {
            $request->session()->forget([
                'password_reset_email',
                'password_reset_verified_until',
            ]);

            return redirect()->route('password.forgot')
                ->withErrors(['email' => 'Your password reset session has expired.']);
        }

        return view('components.auth.new-password-form', [
            'title' => 'Reset Password',
            'email' => $email,
        ]);
    }

    public function updatePassword(Request $request)
    {
        $email = $request->session()->get('password_reset_email');
        $verifiedUntil = $request->session()->get('password_reset_verified_until');

        if (!$email || !$verifiedUntil || now()->greaterThan($verifiedUntil)) {
            $request->session()->forget([
                'password_reset_email',
                'password_reset_verified_until',
            ]);

            return redirect()->route('password.forgot')
                ->withErrors(['email' => 'Your password reset session has expired.']);
        }

        // Validate the password input
        $validated = $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        // Update the user's password
        $user = \App\Models\User::where('email', $email)->firstOrFail();
        $user->password = $validated['password'];
        $user->save();

        $request->session()->forget([
            'password_reset_email',
            'password_reset_verified_until',
        ]);

        return redirect()->route('signin')->with('success', 'Your password has been reset successfully. You can now log in with your new password.');
    }
}
