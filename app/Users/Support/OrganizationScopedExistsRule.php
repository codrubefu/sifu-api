<?php

namespace App\Users\Support;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Builds an `exists` validation rule scoped to an organization, matching the
 * visibility rules enforced by App\Users\Models\Concerns\BelongsToAuthenticatedOrganization.
 *
 * That trait treats rows with a NULL `organization_id` as shared/global records,
 * visible to every organization. An `exists` check that only allows
 * `organization_id = $organizationId` would incorrectly reject those shared rows
 * even though they are legitimately selectable (e.g. returned by list endpoints).
 *
 * This helper keeps the hard tenant boundary — it never allows another
 * organization's non-null rows — while also accepting the shared/global rows,
 * exactly like the trait's global scope does.
 */
class OrganizationScopedExistsRule
{
    public static function make(string $table, string $column, ?int $organizationId): Exists
    {
        return Rule::exists($table, $column)->where(
            fn ($query) => $query->where('organization_id', $organizationId)->orWhereNull('organization_id')
        );
    }
}
