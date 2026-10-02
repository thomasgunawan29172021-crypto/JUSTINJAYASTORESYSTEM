<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\User;
use App\Models\WaitingItem;
use App\Models\WaitingOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WaitingListTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $role = 'crm', ?Branch $branch = null): User
    {
        $branch ??= Branch::create(['name' => 'Cabang tes', 'code' => 'T'.random_int(10000, 99999)]);

        return User::factory()->create(['role' => $role, 'branch_id' => $branch->id, 'is_active' => true]);
    }

    private function payload(): array
    {
        return ['name' => 'Budi', 'phone' => '081234567890', 'branch_id' => auth()->user()->branch_id, 'items' => [
            ['product_name' => 'iPhone 15 128GB', 'quantity' => 2, 'unit_price' => 10000000],
            ['product_name' => 'Samsung A55', 'quantity' => 1, 'unit_price' => 5000000],
        ]];
    }

    public function test_complete_order_flow_and_rendered_pages(): void
    {
        $this->actingAs($this->staff());
        $this->get(route('crm.waiting-list.index'))->assertOk()->assertSee('Belum ada kebutuhan aktif');
        $this->get(route('crm.waiting-list.create'))->assertOk();
        $this->post(route('crm.waiting-list.store'), $this->payload())->assertSessionHasNoErrors()->assertRedirect(route('crm.waiting-list.index'));
        $this->assertDatabaseCount('customers', 1);
        $this->assertDatabaseHas('customer_contacts', ['value' => '6281234567890']);
        $order = WaitingOrder::first();
        $item = $order->items()->first();
        $this->get(route('crm.waiting-list.index'))->assertOk()->assertSee('Budi')->assertSee('20.000.000', false)->assertSee('Waiting List / PO List (2)');
        $this->patch(route('crm.waiting-list.status', $order), ['item_id' => $item->id, 'status' => 'notified'])->assertSessionHasNoErrors();
        $this->assertNotNull($item->fresh()->notified_at);
        $this->assertSame('waiting', $order->items()->latest('id')->first()->status);
        $this->patch(route('crm.waiting-list.status', $order), ['status' => 'purchased'])->assertSessionHasNoErrors();
        $this->assertSame(2, WaitingItem::where('status', 'purchased')->count());
        $this->get(route('crm.waiting-list.index'))->assertOk()->assertDontSee('PO #'.$order->id)->assertSee('Waiting List / PO List (0)');
        $this->get(route('crm.waiting-list.index', ['status' => 'all']))->assertOk()->assertSee('Budi');
        $this->patch(route('crm.waiting-list.status', $order), ['status' => 'waiting']);
        $this->assertNull($item->fresh()->notified_at);
        $this->assertNull($item->fresh()->purchased_at);
        $this->get(route('crm.customers.show', $order->customer_id))->assertOk()->assertSee('mengubah status waiting list');
    }

    public function test_validation_duplicates_and_existing_customers(): void
    {
        $this->actingAs($this->staff());
        $payload = $this->payload();
        $bad = $payload;
        $bad['items'][0]['quantity'] = 0;
        $bad['items'][1]['unit_price'] = -1;
        $this->post(route('crm.waiting-list.store'), $bad)->assertSessionHasErrors(['items.0.quantity', 'items.1.unit_price']);
        $this->assertDatabaseCount('customers', 0);
        $this->post(route('crm.waiting-list.store'), $payload)->assertSessionHasNoErrors();
        $payload['phone'] = '+62 81234567890';
        $this->post(route('crm.waiting-list.store'), $payload)->assertSessionHasErrors('phone');
        $this->assertDatabaseCount('customers', 1);
        $this->assertDatabaseCount('waiting_orders', 1);
        $payload['items'][0]['unit_price'] = '10.000.000';
        $this->post(route('crm.waiting-list.store'), ['customer_id' => Customer::first()->id, 'items' => $payload['items']])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('waiting_orders', 2);
        $this->assertDatabaseHas('waiting_items', ['unit_price' => 10000000]);
        $this->get(route('crm.waiting-list.index', ['product' => 'iphone 15 128gb']))->assertOk()->assertViewHas('orders', fn ($orders) => $orders->total() === 2);
        $this->get(route('crm.waiting-list.index', ['q' => 'does-not-exist']))->assertOk()->assertViewHas('orders', fn ($orders) => $orders->total() === 0);
    }

    public function test_access_scope_and_foreign_item_protection(): void
    {
        $this->get(route('crm.waiting-list.index'))->assertRedirect(route('login'));
        $crm = $this->staff();
        $this->actingAs($crm)->post(route('crm.waiting-list.store'), $this->payload());
        $order = WaitingOrder::first();
        $this->patch(route('crm.waiting-list.status', $order), ['item_id' => 99999, 'status' => 'purchased'])->assertNotFound();
        $this->patch(route('crm.waiting-list.status', $order), ['item_id' => 0, 'status' => 'purchased'])->assertSessionHasErrors('item_id');
        $this->patch(route('crm.waiting-list.status', $order), ['status' => 'invalid'])->assertSessionHasErrors('status');
        $this->actingAs($this->staff('gudang'))->get(route('crm.waiting-list.index'))->assertForbidden();
        $this->post(route('crm.waiting-list.store'), [])->assertForbidden();
        $this->patch(route('crm.waiting-list.status', $order), ['status' => 'purchased'])->assertForbidden();
        $this->actingAs($this->staff('frontliner'));
        $this->get(route('crm.waiting-list.index', ['q' => 'Budi']))->assertForbidden();
        $this->patch(route('crm.waiting-list.status', $order), ['status' => 'purchased'])->assertForbidden();
        $this->post(route('crm.waiting-list.store'), ['customer_id' => $order->customer_id, 'items' => [['product_name' => 'Test', 'quantity' => 1, 'unit_price' => 1]]])->assertForbidden();
        $response = $this->get(route('crm.customers.index', ['q' => '6281234567890']));
        $response->assertOk();
        $this->assertSame(0, $response->viewData('customers')->total());
        $this->get(route('crm.customers.show', $order->customer_id))->assertForbidden();
        $extra = $this->staff('posting');
        $extra->update(['extra_roles' => ['crm']]);
        $extra->refresh();
        $this->assertTrue($extra->hasRole(UserRole::Crm));
        $this->assertSame(1, WaitingOrder::visibleTo($extra)->count());
        $response = $this->actingAs($extra)->get(route('crm.waiting-list.index'));
        $response->assertOk();
        $this->assertSame(1, $response->viewData('orders')->total());
        $this->assertCount(1, $response->viewData('orders')->items());
        $this->assertSame('Budi', $response->viewData('orders')->items()[0]->customer->name);
    }
}
