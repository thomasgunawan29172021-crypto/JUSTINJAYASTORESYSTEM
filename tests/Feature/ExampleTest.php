<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_redirects_guests_to_tracking_page(): void
    {
        $response = $this->get('/');

        $response->assertStatus(302)
            ->assertRedirect(route('track.form'));
    }

    public function test_reminder_generation_is_idempotent(): void
    {
        $branch = Branch::create([
            'name' => 'Cabang Pusat',
            'code' => 'PST',
            'address' => 'Jakarta',
        ]);

        $user = User::factory()->create([
            'role' => UserRole::Frontliner,
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);

        $customer = Customer::register([
            'name' => 'Budi Santoso',
            'address' => 'Jl. Melati No. 1',
            'source' => 'walk-in',
            'branch_id' => $branch->id,
            'notes' => 'Pembeli baru',
        ], [[
            'type' => 'phone',
            'value' => '081234567890',
            'is_primary' => true,
        ]], $user);

        Purchase::record([
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'purchased_at' => now()->subDays(7),
            'notes' => 'Barang masuk',
        ], [[
            'product_name' => 'Headphone Wireless',
            'qty' => 1,
            'unit_price' => 250000,
            'warranty_type' => 'resmi',
            'warranty_until' => now()->subDay()->addYear()->toDateString(),
        ]], $user);

        Artisan::call('reminders:generate');
        $this->assertDatabaseCount('reminders', 1);
        $this->assertSame('h7', Purchase::first()->reminders()->first()->stage);

        Artisan::call('reminders:generate');
        $this->assertDatabaseCount('reminders', 1);
    }
}
