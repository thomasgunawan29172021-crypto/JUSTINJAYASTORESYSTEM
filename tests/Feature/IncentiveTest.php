<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\IncentiveSubmission;
use App\Models\IncentiveTier;
use App\Models\User;
use App\Services\IncentiveCalculatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncentiveTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $staff;
    private User $partner;
    private User $ceo;
    private User $finance;

    protected function setUp(): void
    {
        parent::setUp();
        $this->branch = Branch::create(['name' => 'Cabang Test', 'code' => 'ICT']);
        $this->staff = User::factory()->create(['role' => 'frontliner', 'branch_id' => $this->branch->id, 'is_active' => true, 'promotor_type' => 'fl']);
        $this->partner = User::factory()->create(['role' => 'promotor', 'branch_id' => $this->branch->id, 'is_active' => true, 'promotor_type' => 'brand']);
        $this->ceo = User::factory()->create(['role' => 'ceo', 'branch_id' => $this->branch->id, 'is_active' => true]);
        $this->finance = User::factory()->create(['role' => 'admin_keuangan', 'branch_id' => $this->branch->id, 'is_active' => true]);
    }

    private function payload(array $override = []): array
    {
        return array_replace_recursive([
            'invoice_number' => 'INV-TEST-001', 'branch_id' => $this->branch->id,
            'transaction_date' => today()->toDateString(), 'currency' => 'IDR', 'is_cooperation' => '1',
            'items' => [
                ['product_name' => 'iPhone 15', 'ram' => '8/256', 'color' => 'Black', 'condition' => 'new', 'imei' => '123456', 'capital_price' => '4.000.000', 'sale_price' => '5.000.000'],
                ['product_name' => 'Servis LCD', 'ram' => '', 'color' => '', 'condition' => 'service', 'imei' => '', 'capital_price' => '500.000', 'sale_price' => '1.000.000'],
            ],
            'participants' => [
                ['user_id' => $this->staff->id, 'incentive_type' => 'fl', 'cooperation_role' => 'source'],
                ['user_id' => $this->partner->id, 'incentive_type' => 'brand', 'cooperation_role' => 'closing'],
            ],
            'social_beneficiary_id' => $this->staff->id,
            'social' => ['google' => 'budi.gmaps', 'instagram' => 'budi.ig'],
        ], $override);
    }

    public function test_staff_can_submit_multi_item_cooperation_and_duplicate_invoice_is_rejected(): void
    {
        $this->actingAs($this->staff)->post(route('incentives.store'), $this->payload())->assertSessionHasNoErrors();
        $submission = IncentiveSubmission::firstOrFail();
        $this->assertSame(2, $submission->items()->count());
        $this->assertSame(2, $submission->participants()->count());
        $this->assertSame(2, $submission->socialActions()->count());
        $this->assertEquals(4500000, (float) $submission->total_capital);
        $this->assertEquals(6000000, (float) $submission->total_sales);
        $this->assertEquals(1500000, (float) $submission->total_profit);
        $this->assertEquals([50.0, 50.0], $submission->participants()->orderBy('id')->pluck('share_percent')->map(fn ($v) => (float) $v)->all());

        $this->post(route('incentives.store'), $this->payload())->assertSessionHasErrors('invoice_number');
        $this->assertDatabaseCount('incentive_submissions', 1);
    }

    public function test_only_ceo_decides_and_finance_marks_payment_then_calculation_uses_reached_tiers(): void
    {
        $this->actingAs($this->staff)->post(route('incentives.store'), $this->payload());
        $submission = IncentiveSubmission::firstOrFail();

        $this->patch(route('incentives.decision', $submission), ['decision' => 'approved'])->assertForbidden();
        $this->actingAs($this->ceo)->patch(route('incentives.decision', $submission), ['decision' => 'approved'])->assertSessionHasNoErrors();
        $this->actingAs($this->finance)->patch(route('incentives.payment', $submission), ['payment_status' => 'paid'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('incentive_submissions', ['id' => $submission->id, 'approval_status' => 'approved', 'payment_status' => 'paid']);

        IncentiveTier::create(['branch_id' => $this->branch->id, 'currency' => 'IDR', 'type' => 'fl', 'min_target' => 500000, 'reward_value' => 10]);
        IncentiveTier::create([
            'branch_id' => $this->branch->id, 'currency' => 'IDR', 'type' => 'fl',
            'effective_from' => now()->addMonthNoOverflow()->startOfMonth(),
            'min_target' => 500000, 'reward_value' => 20,
        ]);
        IncentiveTier::create(['branch_id' => $this->branch->id, 'currency' => 'IDR', 'type' => 'brand', 'min_target' => 3000000, 'reward_value' => 1]);
        IncentiveTier::create(['branch_id' => $this->branch->id, 'currency' => 'IDR', 'type' => 'social', 'min_target' => 1, 'reward_value' => 1000]);

        $rows = app(IncentiveCalculatorService::class)->calculate(today()->format('Y-m'));
        $fl = $rows->firstWhere('type', 'fl');
        $brand = $rows->firstWhere('type', 'brand');
        $social = $rows->firstWhere('type', 'social');
        $this->assertEquals(750000, $fl['metric']);
        $this->assertEquals(75000, $fl['incentive']);
        $this->assertEquals(3000000, $brand['metric']);
        $this->assertEquals(30000, $brand['incentive']);
        $this->assertEquals(1, $social['metric']);
        $this->assertEquals(1000, $social['incentive']);

        $nextMonth = now()->addMonthNoOverflow()->startOfMonth();
        $submission->update(['transaction_date' => $nextMonth->toDateString()]);
        $futureFl = app(IncentiveCalculatorService::class)->calculate($nextMonth->format('Y-m'))->firstWhere('type', 'fl');
        $this->assertEquals(150000, $futureFl['incentive']);
    }

    public function test_cooperation_requires_exact_source_and_closing_participants_and_only_ceo_can_edit(): void
    {
        $bad = $this->payload(['participants' => [
            ['user_id' => $this->staff->id, 'incentive_type' => 'fl', 'cooperation_role' => 'source'],
            ['user_id' => $this->partner->id, 'incentive_type' => 'brand', 'cooperation_role' => 'source'],
        ]]);
        $this->actingAs($this->staff)->post(route('incentives.store'), $bad)->assertSessionHasErrors('participants');
        $this->post(route('incentives.store'), $this->payload())->assertSessionHasNoErrors();
        $submission = IncentiveSubmission::firstOrFail();
        $this->get(route('incentives.edit', $submission))->assertForbidden();
        $this->actingAs($this->ceo)->get(route('incentives.edit', $submission))->assertOk();
    }

    public function test_promotor_type_is_automatic_for_staff_and_non_promotor_is_rejected(): void
    {
        $spoofed = $this->payload(['participants' => [
            ['user_id' => $this->staff->id, 'incentive_type' => 'brand', 'cooperation_role' => 'source'],
            ['user_id' => $this->partner->id, 'incentive_type' => 'fl', 'cooperation_role' => 'closing'],
        ]]);
        $this->actingAs($this->staff)->post(route('incentives.store'), $spoofed)->assertSessionHasNoErrors();
        $this->assertSame(
            ['fl', 'brand'],
            IncentiveSubmission::firstOrFail()->participants()->orderBy('id')->pluck('incentive_type')->all()
        );

        $nonPromotor = User::factory()->create([
            'role' => 'frontliner', 'branch_id' => $this->branch->id, 'is_active' => true, 'promotor_type' => null,
        ]);
        $invalid = $this->payload([
            'invoice_number' => 'INV-NON-PROMOTOR',
            'participants' => [
                ['user_id' => $nonPromotor->id, 'incentive_type' => 'fl', 'cooperation_role' => 'source'],
                ['user_id' => $this->partner->id, 'incentive_type' => 'brand', 'cooperation_role' => 'closing'],
            ],
        ]);
        $this->post(route('incentives.store'), $invalid)->assertSessionHasErrors('participants.0.user_id');
        $this->assertDatabaseMissing('incentive_submissions', ['invoice_number' => 'INV-NON-PROMOTOR']);
    }

    public function test_ceo_can_set_promotor_type_from_user_management(): void
    {
        $this->actingAs($this->ceo)->put(route('users.update', $this->staff), [
            'name' => $this->staff->name,
            'email' => $this->staff->email,
            'phone' => $this->staff->phone,
            'password' => '',
            'role' => $this->staff->role->value,
            'branch_id' => $this->branch->id,
            'branch_ids' => [$this->branch->id],
            'base_salary' => 0,
            'promotor_type' => 'brand',
            'is_active' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['id' => $this->staff->id, 'promotor_type' => 'brand']);
        $this->get(route('users.index', ['promotor_type' => 'brand']))
            ->assertOk()->assertSee($this->staff->name)->assertSee('Promotor Brand');
    }

    public function test_setting_edits_are_versioned_for_next_month_and_can_be_copied_to_all_branches(): void
    {
        $historical = IncentiveTier::create([
            'branch_id' => $this->branch->id, 'currency' => 'IDR', 'type' => 'fl',
            'label' => 'Lama', 'min_target' => 2500000, 'reward_value' => 10,
        ]);
        $otherBranch = Branch::create(['name' => 'Cabang Lain', 'code' => 'IC2']);
        $effectiveDate = now()->addMonthNoOverflow()->startOfMonth()->toDateString();

        $this->actingAs($this->ceo)->put(route('incentives.settings.update', $historical), [
            'branch_id' => $this->branch->id,
            'currency' => 'IDR',
            'type' => 'fl',
            'label' => 'Baru',
            'min_target' => '2.500.000',
            'reward_value' => '11,5',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('incentive_tiers', [
            'id' => $historical->id, 'effective_from' => '2000-01-01', 'reward_value' => 10,
        ]);
        $futureTier = IncentiveTier::where('branch_id', $this->branch->id)
            ->whereDate('effective_from', $effectiveDate)->where('type', 'fl')
            ->where('min_target', 2500000)->where('reward_value', 11.5)->firstOrFail();
        $this->get(route('incentives.settings', ['edit' => $futureTier->id]))
            ->assertOk()->assertSee('Edit data')->assertSee('value="2.500.000"', false);

        $this->post(route('incentives.settings.copy-all'), [
            'source_branch_id' => $this->branch->id, 'currency' => 'IDR', 'type' => 'fl',
        ])->assertSessionHasNoErrors();
        $this->assertTrue(IncentiveTier::where('branch_id', $otherBranch->id)
            ->whereDate('effective_from', $effectiveDate)->where('type', 'fl')
            ->where('min_target', 2500000)->where('reward_value', 11.5)->exists());
    }

    public function test_all_incentive_pages_render_for_their_authorized_roles(): void
    {
        $this->actingAs($this->staff);
        $this->get(route('incentives.index'))->assertOk()->assertSee('Insentif');
        $this->get(route('incentives.create'))->assertOk()->assertSee('Ajukan insentif');

        $this->post(route('incentives.store'), $this->payload())->assertSessionHasNoErrors();
        $submission = IncentiveSubmission::firstOrFail();
        $this->get(route('incentives.show', $submission))->assertOk()->assertSee('INV-TEST-001');
        $this->get(route('incentives.settings'))->assertForbidden();

        $this->actingAs($this->finance);
        $this->get(route('incentives.index'))->assertOk()->assertSee('INV-TEST-001');

        $this->actingAs($this->ceo);
        $this->get(route('incentives.index'))->assertOk();
        $this->get(route('incentives.settings'))->assertOk()->assertSee('Pengaturan target insentif');
        $this->get(route('incentives.edit', $submission))->assertOk()->assertSee('Edit pengajuan');
    }
}
