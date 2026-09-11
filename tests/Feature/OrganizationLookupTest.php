<?php

namespace Tests\Feature;

use App\Users\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_organization_can_be_found_by_slug_without_authentication(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Acme SRL',
            'slug' => 'acme',
            'url' => 'https://acme.test',
            'address' => 'Str. Exemplu 1, Bucuresti',
            'email' => 'office@acme.test',
            'phone' => '0712345678',
            'web' => 'https://acme.test',
            'cui' => 'RO12345678',
            'nr_reg_com' => 'J40/1234/2026',
            'capital' => '200 RON',
            'cont' => 'RO49AAAA1B31007593840000',
            'bank' => 'Banca Test',
            'receipt_code' => 'CH',
            'receipt_number' => 12,
            'invoice_code' => 'INV',
            'invoice_number' => 34,
            'bill_code' => 'BILL',
            'bill_number' => 56,
        ]);

        $this->getJson('/api/organizations/slug/acme')
            ->assertOk()
            ->assertJsonPath('data.id', $organization->id)
            ->assertJsonPath('data.slug', 'acme')
            ->assertJsonPath('data.url', 'https://acme.test')
            ->assertJsonPath('data.name', 'Acme SRL')
            ->assertJsonPath('data.address', 'Str. Exemplu 1, Bucuresti')
            ->assertJsonPath('data.email', 'office@acme.test')
            ->assertJsonPath('data.phone', '0712345678')
            ->assertJsonPath('data.web', 'https://acme.test')
            ->assertJsonPath('data.cui', 'RO12345678')
            ->assertJsonPath('data.nr_reg_com', 'J40/1234/2026')
            ->assertJsonPath('data.capital', '200 RON')
            ->assertJsonPath('data.cont', 'RO49AAAA1B31007593840000')
            ->assertJsonPath('data.bank', 'Banca Test')
            ->assertJsonPath('data.receipt_code', 'CH')
            ->assertJsonPath('data.receipt_number', 12)
            ->assertJsonPath('data.invoice_code', 'INV')
            ->assertJsonPath('data.invoice_number', 34)
            ->assertJsonPath('data.bill_code', 'BILL')
            ->assertJsonPath('data.bill_number', 56);
    }

    public function test_organization_slug_lookup_returns_not_found_for_unknown_slug(): void
    {
        $this->getJson('/api/organizations/slug/missing')
            ->assertNotFound();
    }

    public function test_organization_can_be_found_by_exact_url_without_authentication(): void
    {
        $organization = Organization::factory()->create([
            'slug' => 'acme',
            'url' => 'https://acme.test',
        ]);

        $this->getJson('/api/organizations/by-url?url=https://acme.test')
            ->assertOk()
            ->assertJsonPath('data.id', $organization->id)
            ->assertJsonPath('data.slug', 'acme')
            ->assertJsonPath('data.url', 'https://acme.test');
    }

    public function test_organization_by_url_lookup_ignores_trailing_slash_in_query(): void
    {
        $organization = Organization::factory()->create([
            'slug' => 'acme',
            'url' => 'https://acme.test',
        ]);

        $this->getJson('/api/organizations/by-url?url=https://acme.test/')
            ->assertOk()
            ->assertJsonPath('data.id', $organization->id);
    }

    public function test_organization_by_url_lookup_ignores_trailing_slash_in_stored_value(): void
    {
        // The `saving` hook normally strips the trailing slash on write; force it
        // directly on the column to also cover any pre-existing/legacy data.
        $organization = Organization::factory()->create([
            'slug' => 'acme',
            'url' => 'https://acme.test',
        ]);
        $organization->newQueryWithoutScopes()->where('id', $organization->id)->update(['url' => 'https://acme.test/']);

        $this->getJson('/api/organizations/by-url?url=https://acme.test')
            ->assertOk()
            ->assertJsonPath('data.id', $organization->id);
    }

    public function test_organization_by_url_lookup_is_case_insensitive(): void
    {
        $organization = Organization::factory()->create([
            'slug' => 'acme',
            'url' => 'https://acme.test',
        ]);

        $this->getJson('/api/organizations/by-url?url=HTTPS://ACME.TEST')
            ->assertOk()
            ->assertJsonPath('data.id', $organization->id);
    }

    public function test_organization_by_url_lookup_returns_not_found_for_unknown_url(): void
    {
        $this->getJson('/api/organizations/by-url?url=https://unknown.test')
            ->assertNotFound();
    }

    public function test_organization_by_url_lookup_is_callable_without_authorization_header(): void
    {
        $organization = Organization::factory()->create([
            'slug' => 'acme',
            'url' => 'https://acme.test',
        ]);

        $this->withHeaders(['Authorization' => ''])
            ->getJson('/api/organizations/by-url?url=https://acme.test')
            ->assertOk()
            ->assertJsonPath('data.id', $organization->id);
    }
}
