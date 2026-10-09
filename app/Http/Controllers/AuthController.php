<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        // one message for unknown email, wrong password and inactive account,
        // so the response never reveals which accounts exist
        if (! $user || ! Hash::check($request->password, $user->password) || $user->status !== 'active') {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'data' => [
                'token' => $user->createToken('api-login')->plainTextToken,
            ],
        ]);
    }

    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    public function me(Request $request): UserResource
    {
        return UserResource::make($this->withStudentId($request->user()))->additional([
            'success' => true,
            'message' => 'Current user retrieved successfully.',
        ]);
    }

    public function updateMe(UpdateProfileRequest $request): UserResource
    {
        $user = $request->user();
        $data = $request->validated();

        DB::transaction(function () use ($user, $data) {
            $user->update($data);

            // keep the student record's email in step with the login email
            if (isset($data['email']) && $user->student) {
                $user->student->update(['email' => $data['email']]);
            }
        });

        return UserResource::make($this->withStudentId($user->refresh()))->additional([
            'success' => true,
            'message' => 'Profile updated successfully.',
        ]);
    }

    /**
     * Student clients need their student record id for every self-service route.
     */
    private function withStudentId(User $user): User
    {
        if ($user->role === 'student') {
            $user->loadMissing('student');
        }

        return $user;
    }
}
