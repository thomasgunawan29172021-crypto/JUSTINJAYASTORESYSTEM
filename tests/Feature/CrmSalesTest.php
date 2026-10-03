<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Purchase;
use App\Models\Reminder;
use App\Models\User;
use App\Models\WaitingItem;
use App\Models\WaitingOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmSalesTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $role = 'crm', ?Branch $branch = null): User
    {
        $branch ??= Branch::create(['name' => 'Cabang '.uniqid(), 'code' => strtoupper(substr(uniqid(), -6))]);

        return User::factory()->create(['role' => $role, 'branch_id' => $branch->id, 'is_active' => true]);
    }

    private function payload(Branch $branch, array $extra = []): array
    {
        return array_replace_recursive([
            'phone' => '0812-3456-7890', 'name' => 'Budi Santoso', 'secondary_phone' => '+62 813 1111 2222',
            'address' => 'Ilir Barat, Palembang', 'source' => 'TikTok', 'branch_id' => $branch->id,
            'purchased_at' => today()->subDays(8)->toDateString(), 'payment_method' => 'transfer', 'notes' => 'Pelanggan tertarik upgrade.',
            'items' => [
                ['product_name' => 'iPhone 15 128GB', 'brand' => 'Apple', 'product_type' => 'Smartphone', 'quantity' => 1, 'unit_price' => '10.000.000'],
                ['product_name' => 'Case iPhone 15', 'brand' => 'No Brand', 'product_type' => 'Aksesori', 'quantity' => 2, 'unit_price' => '150.000'],
            ],
        ], $extra);
    }

    public function test_complete_sale_lookup_followup_and_customer_history_flow(): void
    {
        $branch = Branch::create(['name' => 'Cabang Utama', 'code' => 'UTM']);
        $crm = $this->staff('crm', $branch);
        $this->actingAs($crm);

        $this->get(route('crm.sales.create'))->assertOk()->assertSee('Catat penjualan');
        $this->post(route('crm.sales.store'), $this->payload($branch))->assertSessionHasNoErrors();

        $this->assertDatabaseCount('customers', 1);
        $this->assertDatabaseCount('customer_contacts', 2);
        $this->assertDatabaseHas('customer_contacts', ['value' => '6281234567890']);
        $this->assertDatabaseHas('customer_contacts', ['value' => '6281311112222']);
        $this->assertDatabaseCount('purchases', 1);
        $this->assertDatabaseHas('purchases', ['payment_method' => 'transfer', 'total_amount' => 10300000]);
        $this->assertDatabaseHas('purchase_items', ['product_name' => 'iPhone 15 128GB', 'unit_price' => 10000000]);
        $this->assertDatabaseCount('reminders', 4);

        $purchase = Purchase::first();
        $this->get(route('crm.sales.show', $purchase))->assertOk()->assertSee('Rp 10.300.000', false)->assertSee('7 hari setelah pembelian');
        $this->getJson(route('crm.sales.customer-lookup', ['phone' => '+62 812 3456 7890']))
            ->assertOk()->assertJsonPath('customers.0.name', 'Budi Santoso')->assertJsonPath('customers.0.id', $purchase->customer_id);
        $this->get(route('crm.sales.index', ['brand' => 'Apple', 'source' => 'TikTok', 'domicile' => 'Palembang', 'min_total' => '10.000.000']))
            ->assertOk()->assertSee('Budi Santoso')->assertViewHas('summary', fn ($summary) => $summary['revenue'] === 10300000.0);
        $this->get(route('crm.customers.show', $purchase->customer))->assertOk()->assertSee('iPhone 15 128GB')->assertSee('7 hari setelah pembelian');

        $due = Reminder::where('type', 'after_7_days')->first();
        $this->get(route('crm.follow-ups.index'))->assertOk()->assertSee('Budi Santoso')->assertSee('Terlambat');
        $this->patch(route('crm.follow-ups.update', $due), ['status' => 'completed', 'note' => 'Pelanggan puas'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('reminders', ['id' => $due->id, 'status' => 'completed', 'completed_by' => $crm->id]);
        $this->assertDatabaseHas('customer_histories', ['customer_id' => $purchase->customer_id, 'action' => 'followup_completed']);
    }

    public function test_existing_customer_is_reused_and_matching_waiting_item_is_completed(): void
    {
        $branch = Branch::create(['name' => 'Cabang Satu', 'code' => 'CS1']);
        $crm = $this->staff('crm', $branch);
        $customer = Customer::register(['name' => 'Siti', 'branch_id' => $branch->id, 'source' => 'Facebook'], [['type' => 'phone', 'value' => '081299988877', 'is_primary' => true]], $crm);
        $order = WaitingOrder::create(['customer_id' => $customer->id, 'created_by' => $crm->id]);
        $item = $order->items()->create(['product_name' => 'iPhone 15 128GB', 'product_key' => 'iphone 15 128gb', 'quantity' => 1, 'unit_price' => 9000000, 'status' => 'waiting']);

        $payload = $this->payload($branch, ['phone' => '+62 812 9998 8877', 'name' => null, 'secondary_phone' => null]);
        $this->actingAs($crm)->post(route('crm.sales.store'), $payload)->assertSessionHasNoErrors();

        $this->assertDatabaseCount('customers', 1);
        $this->assertSame('purchased', $item->fresh()->status);
        $this->assertNotNull($item->fresh()->purchased_at);
        $this->assertDatabaseHas('customer_histories', ['customer_id' => $customer->id, 'action' => 'waiting_status']);
    }

    public function test_access_branch_scope_validation_and_ceo_only_mutation(): void
    {
        $branchA = Branch::create(['name' => 'A', 'code' => 'BRA']);
        $branchB = Branch::create(['name' => 'B', 'code' => 'BRB']);
        $headA = $this->staff('kepala_toko', $branchA);
        $headB = $this->staff('kepala_toko', $branchB);
        $crm = $this->staff('crm', $branchB);

        $this->actingAs($headA)->post(route('crm.sales.store'), $this->payload($branchA))->assertSessionHasNoErrors();
        $purchase = Purchase::first();
        $this->get(route('crm.sales.show', $purchase))->assertOk();
        $this->actingAs($headB)->get(route('crm.sales.show', $purchase))->assertNotFound();
        $this->get(route('crm.sales.index'))->assertOk()->assertDontSee('Budi Santoso');
        $this->post(route('crm.sales.store'), $this->payload($branchA, ['phone' => '081200000001', 'secondary_phone' => null]))->assertForbidden();
        $frontliner = $this->staff('frontliner', $branchA);
        $this->actingAs($frontliner)->get(route('crm.sales.index'))->assertForbidden();
        $this->get(route('crm.customers.show', $purchase->customer))->assertOk()->assertDontSee('Riwayat Transaksi');

        $this->actingAs($crm)->get(route('crm.sales.show', $purchase))->assertOk();
        $this->get(route('crm.sales.edit', $purchase))->assertForbidden();
        $this->delete(route('crm.sales.destroy', $purchase))->assertForbidden();

        $bad = $this->payload($branchB, ['phone' => '123', 'items' => [['product_name' => 'X', 'quantity' => 1, 'unit_price' => 0]]]);
        $this->post(route('crm.sales.store'), $bad)->assertSessionHasErrors(['items.0.unit_price']);

        $ceo = $this->staff('ceo', $branchA);
        $this->actingAs($ceo)->get(route('crm.sales.edit', $purchase))->assertOk();
        $this->delete(route('crm.sales.destroy', $purchase))->assertRedirect(route('crm.sales.index'));
        $this->assertSoftDeleted('purchases', ['id' => $purchase->id]);
    }
}
