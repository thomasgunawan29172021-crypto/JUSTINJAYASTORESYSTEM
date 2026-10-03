<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerSegmentationTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $role, Branch $branch): User
    {
        return User::factory()->create(['role' => $role, 'branch_id' => $branch->id, 'is_active' => true]);
    }

    private function customerPayload(Branch $branch): array
    {
        return [
            'name' => 'Andi Samsung', 'address' => 'Jl. Sudirman, Ilir Barat', 'city' => 'Palembang',
            'source' => 'tiktok', 'branch_id' => $branch->id, 'notes' => 'Suka HP kelas menengah',
            'contact_type' => ['whatsapp'], 'contact_value' => ['081234567801'], 'contact_primary' => 0,
            'purchase_product_name' => 'Samsung Galaxy A56', 'purchase_brand' => 'Samsung',
            'purchase_product_type' => 'Smartphone', 'purchase_unit_price' => '7.000.000',
            'purchase_payment_method' => 'credit', 'purchase_date' => today()->subDay()->toDateString(),
        ];
    }

    public function test_customer_can_be_created_with_segmentable_initial_purchase(): void
    {
        $branch = Branch::create(['name' => 'Cabang Ilir', 'code' => 'ILR']);
        $crm = $this->staff('crm', $branch);

        $this->actingAs($crm)->get(route('crm.customers.create'))->assertOk()
            ->assertSee('Transaksi awal')->assertSee('purchase_product_name', false)->assertSee('Kota / area');
        $this->post(route('crm.customers.store'), $this->customerPayload($branch))->assertSessionHasNoErrors();

        $customer = Customer::first();
        $this->assertSame('Palembang', $customer->city);
        $this->assertDatabaseHas('purchases', ['customer_id' => $customer->id, 'payment_method' => 'credit', 'total_amount' => 7000000]);
        $this->assertDatabaseHas('purchase_items', ['product_name' => 'Samsung Galaxy A56', 'brand' => 'Samsung', 'product_type' => 'Smartphone', 'unit_price' => 7000000]);
        $this->assertDatabaseCount('reminders', 4);
    }

    public function test_customer_filters_sort_and_excel_export_follow_active_segment(): void
    {
        $branchA = Branch::create(['name' => 'Cabang Ilir', 'code' => 'ILR']);
        $branchB = Branch::create(['name' => 'Cabang Jakarta', 'code' => 'JKT']);
        $crm = $this->staff('crm', $branchA);
        $this->actingAs($crm)->post(route('crm.customers.store'), $this->customerPayload($branchA))->assertSessionHasNoErrors();
        $other = Customer::register(['name' => 'Zelda Apple', 'city' => 'Jakarta', 'source' => 'referral', 'branch_id' => $branchB->id], [['type' => 'phone', 'value' => '081234567802', 'is_primary' => true]], $crm);

        $filters = ['brand' => 'Samsung', 'source' => 'tiktok', 'payment_method' => 'credit', 'branch_id' => $branchA->id, 'min_price' => '6.000.000', 'max_price' => '8.000.000', 'city' => 'Palembang'];
        $response = $this->get(route('crm.customers.index', $filters));
        $response->assertOk()->assertSee('Andi Samsung')->assertDontSee($other->name)
            ->assertViewHas('customers', fn ($customers) => $customers->total() === 1);

        $this->get(route('crm.customers.index', ['sort' => 'name_desc']))->assertViewHas('customers', fn ($customers) => $customers->first()->name === 'Zelda Apple');
        $this->get(route('crm.customers.index', ['sort' => 'name_asc']))->assertViewHas('customers', fn ($customers) => $customers->first()->name === 'Andi Samsung');

        $export = $this->get(route('crm.customers.export', $filters));
        $export->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringStartsWith("PK\x03\x04", $export->getContent());
        $this->assertStringContainsString('.xlsx', $export->headers->get('content-disposition'));
        $archivePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'customer-export-'.uniqid().'.zip';
        file_put_contents($archivePath, $export->getContent());
        try {
            $archive = new \PharData($archivePath);
            $this->assertTrue(isset($archive['xl/workbook.xml']));
            $this->assertTrue(isset($archive['xl/worksheets/sheet1.xml']));
            $this->assertStringContainsString('Samsung Galaxy A56', $archive['xl/worksheets/sheet1.xml']->getContent());
            $this->assertStringNotContainsString('Zelda Apple', $archive['xl/worksheets/sheet1.xml']->getContent());
        } finally {
            @unlink($archivePath);
        }

        $frontliner = $this->staff('frontliner', $branchA);
        $this->actingAs($frontliner)->get(route('crm.customers.export'))->assertForbidden();
        $this->assertSame(1, Purchase::count());
    }
}
