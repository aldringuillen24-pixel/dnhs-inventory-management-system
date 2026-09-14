<?php

use App\Mail\PasswordResetOtpMail;
use App\Models\PasswordResetOTP;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

function passwordResetUser(): User
{
    return User::factory()->create([
        'email' => 'reset@example.com',
    ]);
}

test('requesting a password reset sends an otp email and stores a hashed otp', function () {
    Mail::fake();
    $user = passwordResetUser();

    $response = $this->post(route('password.email'), [
        'email' => $user->email,
    ]);

    $response->assertRedirect(route('password.otp', ['email' => $user->email]));
    Mail::assertSent(PasswordResetOtpMail::class, fn ($mail) => $mail->otp >= 100000 && $mail->otp <= 999999);

    $otpRecord = PasswordResetOTP::where('email', $user->email)->firstOrFail();

    expect(Hash::check((string) Mail::sent(PasswordResetOtpMail::class)->first()->otp, $otpRecord->otp_hash))->toBeTrue();
});

test('a valid otp authorizes a password reset and the password can be updated', function () {
    Mail::fake();
    $user = passwordResetUser();

    $this->post(route('password.email'), ['email' => $user->email]);
    $otp = Mail::sent(PasswordResetOtpMail::class)->first()->otp;

    $verifyResponse = $this->post(route('password.otp.verify'), [
        'email' => $user->email,
        'otp' => $otp,
    ]);

    $verifyResponse->assertRedirect(route('password.reset.form', ['email' => $user->email]));

    $resetResponse = $this->post(route('password.update'), [
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ]);

    $resetResponse->assertRedirect(route('signin'));
    expect(Hash::check('new-password-123', $user->refresh()->password))->toBeTrue();
    $resetResponse->assertSessionMissing('password_reset_email');
});

test('otp verification page shows a live expiry countdown', function () {
    $user = passwordResetUser();

    PasswordResetOTP::create([
        'email' => $user->email,
        'otp_hash' => Hash::make('123456'),
        'expires_at' => now()->addMinutes(5),
        'attempts' => 0,
    ]);

    $response = $this->get(route('password.otp', ['email' => $user->email]));

    $response->assertOk()
        ->assertSee('Your code expires in')
        ->assertSee('otp-countdown')
        ->assertSee('data-expires-at');
});

test('an expired otp is rejected', function () {
    $user = passwordResetUser();

    PasswordResetOTP::create([
        'email' => $user->email,
        'otp_hash' => Hash::make('123456'),
        'expires_at' => now()->subMinute(),
        'attempts' => 0,
    ]);

    $response = $this->post(route('password.otp.verify'), [
        'email' => $user->email,
        'otp' => '123456',
    ]);

    $response->assertSessionHasErrors('otp');
});

test('password reset cannot be performed without otp verification', function () {
    $user = passwordResetUser();

    $response = $this->post(route('password.update'), [
        'email' => $user->email,
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ]);

    $response->assertRedirect(route('password.forgot'));
    expect(Hash::check('password', $user->refresh()->password))->toBeTrue();
});

test('otp verification is blocked after five failed attempts', function () {
    $user = passwordResetUser();

    PasswordResetOTP::create([
        'email' => $user->email,
        'otp_hash' => Hash::make('123456'),
        'expires_at' => now()->addMinutes(5),
        'attempts' => 0,
    ]);

    foreach (range(1, 5) as $attempt) {
        $this->post(route('password.otp.verify'), [
            'email' => $user->email,
            'otp' => '000000',
        ]);
    }

    expect(PasswordResetOTP::first()->attempts)->toBe(5);

    $response = $this->post(route('password.otp.verify'), [
        'email' => $user->email,
        'otp' => '123456',
    ]);

    $response->assertStatus(429);
});
