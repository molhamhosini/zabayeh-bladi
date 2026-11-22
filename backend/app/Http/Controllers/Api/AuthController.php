<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    /** UAE mobile numbers, normalised to +9715XXXXXXXX. */
    private function phone(Request $request): string
    {
        $request->validate(['phone' => ['required', 'string']]);
        $digits = preg_replace('/\D/', '', $request->string('phone'));
        $digits = preg_replace('/^(00971|971|0)/', '', $digits);
        if (! preg_match('/^5\d{8}$/', $digits)) {
            abort(response()->json(['message' => 'رقم الجوال غير صحيح', 'errors' => ['phone' => ['رقم الجوال غير صحيح']]], 422));
        }

        return '+971'.$digits;
    }

    public function requestOtp(Request $request, OtpService $otp): JsonResponse
    {
        $otp->send($this->phone($request));

        return response()->json(['message' => 'تم إرسال رمز التحقق']);
    }

    public function verifyOtp(Request $request, OtpService $otp): JsonResponse
    {
        $phone = $this->phone($request);
        $request->validate(['code' => ['required', 'digits:'.config('shop.otp.length')], 'name' => ['nullable', 'string', 'max:100']]);

        if (! $otp->verify($phone, $request->string('code'))) {
            return response()->json(['message' => 'رمز التحقق غير صحيح أو منتهي', 'errors' => ['code' => ['رمز التحقق غير صحيح أو منتهي']]], 422);
        }

        $user = User::firstOrCreate(['phone' => $phone], ['name' => $request->input('name') ?: $phone, 'role' => User::ROLE_CUSTOMER]);

        return response()->json([
            'token' => $user->createToken('mobile')->plainTextToken,
            'user' => $user->only('id', 'name', 'phone', 'email', 'locale'),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user()->only('id', 'name', 'phone', 'email', 'locale'));
    }

    public function updateMe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'email' => ['sometimes', 'nullable', 'email', 'unique:users,email,'.$request->user()->id],
            'locale' => ['sometimes', 'in:ar,en'],
        ]);
        $request->user()->update($data);

        return $this->me($request);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'ok']);
    }
}
