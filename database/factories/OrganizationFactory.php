<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Users\Models\Organization>
 */
class OrganizationFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1000, 9999),
            'url' => fake()->url(),
            'description' => fake()->sentence(),
            'address' => fake()->address(),
            'email' => fake()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'web' => fake()->url(),
            'cui' => 'RO'.fake()->unique()->numberBetween(1000000, 99999999),
            'nr_reg_com' => 'J'.fake()->numberBetween(1, 52).'/'.fake()->numberBetween(1, 9999).'/'.fake()->year(),
            'capital' => '200 RON',
            'cont' => fake()->iban('RO'),
            'bank' => fake()->company().' Bank',
            'receipt_code' => 'CH',
            'receipt_number' => 0,
            'invoice_code' => 'INV',
            'invoice_number' => 0,
            'bill_code' => 'BILL',
            'bill_number' => 0,
        ];
    }
}
