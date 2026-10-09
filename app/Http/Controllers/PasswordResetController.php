<?php

namespace App\Http\Controllers;

use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\ResetPasswordRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class PasswordResetController extends Controller
{
    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        // the broker's status is deliberately ignored: the response must be
        // identical whether or not the email belongs to an account
        Password::sendResetLink($request->only('email'));

        return response()->json([
            'success' => true,
            'message' => 'If that email is registered, a reset link has been sent.',
        ]);
    }

    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, string $password) {
                // 'password' => 'hashed' cast on User hashes this for us
                $user->forceFill(['password' => $password])->save();

                // a stolen token must not outlive a password reset
                $user->tokens()->delete();
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Password has been reset.',
        ]);
    }
}
