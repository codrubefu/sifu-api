<?php

namespace App\Users\Http\Controllers\Api;

use App\Users\Http\Controllers\Controller;
use App\Users\Http\Requests\ForgotPasswordRequest;
use App\Users\Http\Requests\ResetPasswordRequest;
use App\Users\Mail\PasswordSetupMail;
use App\Users\Models\User;
use App\Users\Services\PasswordSetupTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PasswordResetController extends Controller
{
    private const GENERIC_SENT_MESSAGE = 'Dacă adresa de email este asociată unui cont, vei primi un link de resetare a parolei.';

    public function __construct(
        private readonly PasswordSetupTokenService $passwordSetupTokens,
    ) {
    }

    public function sendResetLink(ForgotPasswordRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::query()
            ->where('email', $data['email'])
            ->where('organization_id', $data['organization_id'])
            ->first();

        if ($user && $user->active && filled($user->email)) {
            $token = $this->passwordSetupTokens->generate($user);

            Mail::to($user->email)->queue(new PasswordSetupMail($user, $token, isNewAccount: false));
        }

        $this->logAttempt($request, 'forgot_password', $user?->id, (int) $data['organization_id'], $data['email']);

        return response()->json([
            'message' => self::GENERIC_SENT_MESSAGE,
        ]);
    }

    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::query()
            ->where('email', $data['email'])
            ->where('organization_id', $data['organization_id'])
            ->first();

        $succeeded = $user && $this->passwordSetupTokens->complete($user, $data['token'], $data['password']);

        $this->logAttempt($request, 'reset_password', $user?->id, (int) $data['organization_id'], $data['email'], $succeeded);

        if (! $succeeded) {
            return response()->json([
                'message' => 'Linkul de resetare este invalid sau a expirat.',
            ], 422);
        }

        return response()->json([
            'message' => 'Parola a fost setată cu succes.',
        ]);
    }

    private function logAttempt(Request $request, string $action, ?int $userId, int $organizationId, string $declaredIdentity, ?bool $successful = null): void
    {
        Log::info('Password reset attempt', [
            'action' => $action,
            'successful' => $successful,
            'user_id' => $userId,
            'organization_id' => $organizationId,
            'declared_identity_hash' => hash('sha256', mb_strtolower($declaredIdentity)),
            'ip' => $request->ip(),
        ]);
    }
}
