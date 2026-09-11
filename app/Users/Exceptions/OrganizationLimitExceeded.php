<?php

namespace App\Users\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class OrganizationLimitExceeded extends RuntimeException
{
    public function __construct(public readonly array $details)
    {
        parent::__construct($details['message']);
    }

    public function render(): JsonResponse
    {
        return response()->json($this->details, 409);
    }
}
