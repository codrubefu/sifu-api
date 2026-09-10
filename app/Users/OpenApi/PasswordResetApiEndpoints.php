<?php

namespace App\Users\OpenApi;

use OpenApi\Attributes as OA;

class PasswordResetApiEndpoints
{
    #[OA\Post(
        path: '/password/forgot',
        summary: 'Request a password reset link',
        description: 'Public endpoint, throttled with the `login` limiter. Always responds with the same generic message whether or not the account exists, to avoid user enumeration. When the account exists and is active, queues the same password-setup e-mail sent on user creation, linking to the organization\'s own frontend URL (falling back to the system default when the organization has none configured).',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'organization_id'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'test@example.com'),
                    new OA\Property(property: 'organization_id', type: 'integer', example: 1),
                ],
                type: 'object',
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Generic confirmation message.',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Daca adresa de email este asociata unui cont, vei primi un link de resetare a parolei.')], type: 'object'),
            ),
            new OA\Response(response: 422, description: 'Validation failed.'),
            new OA\Response(response: 429, description: 'Login rate limit exceeded for this IP, organization and declared identity.'),
        ],
    )]
    public function sendResetLink(): void {}

    #[OA\Post(
        path: '/password/reset',
        summary: 'Set a new password using a reset/setup token',
        description: 'Public endpoint, throttled with the `login` limiter. Consumes a single-use `password_setup_tokens` token (keyed by user, not e-mail) and sets the new password. Password strength is validated with the beneficiary-appropriate policy (administrator vs operator). On success, all of the user\'s existing bearer tokens are revoked.',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'organization_id', 'token', 'password', 'password_confirmation'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'test@example.com'),
                    new OA\Property(property: 'organization_id', type: 'integer', example: 1),
                    new OA\Property(property: 'token', type: 'string', example: '4f3c2e1a...'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'Str0ngPassw0rd!'),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', example: 'Str0ngPassw0rd!'),
                ],
                type: 'object',
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Password set successfully.',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Parola a fost setata cu succes.')], type: 'object'),
            ),
            new OA\Response(response: 422, description: 'Validation failed, or the token is invalid, expired, or already used.'),
            new OA\Response(response: 429, description: 'Login rate limit exceeded for this IP, organization and declared identity.'),
        ],
    )]
    public function reset(): void {}
}
