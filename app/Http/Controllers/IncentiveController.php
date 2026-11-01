<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\IncentiveSubmission;
use App\Models\User;
use App\Services\IncentiveCalculatorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class IncentiveController extends Controller
{
    public function index(Request $request, IncentiveCalculatorService $calculator)
    {
        $filters = $request->validate([
            'period' => ['nullable', 'date_format:Y-m'], 'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'status' => ['nullable', Rule::in(['pending', 'approved', 'rejected'])],
            'payment' => ['nullable', Rule::in(['paid', 'unpaid'])], 'q' => ['nullable', 'string', 'max:100'],
        ]);
        $period = $filters['period'] ?? now()->format('Y-m');
        $branchId = isset($filters['branch_id']) ? (int) $filters['branch_id'] : null;
        $user = $request->user();

        $submissions = IncentiveSubmission::visibleTo($user)
            ->with(['branch', 'submitter', 'participants.user'])
            ->whereBetween('transaction_date', [$period.'-01', date('Y-m-t', strtotime($period.'-01'))])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('approval_status', $v))
            ->when($filters['payment'] ?? null, fn ($q, $v) => $q->where('payment_status', $v))
            ->when($filters['q'] ?? null, fn ($q, $v) => $q->where(fn ($s) => $s->where('invoice_number', 'like', '%'.$v.'%')
                ->orWhereHas('items', fn ($i) => $i->where('product_name', 'like', '%'.$v.'%'))))
            ->latest('transaction_date')->latest('id')->paginate(20)->withQueryString();

        $summaryUserId = ($user->isCeo() || $user->canReviewIncentivePayment()) ? null : $user->id;
        $summary = $calculator->calculate($period, $branchId, $summaryUserId);

        return view('incentives.index', [
            'submissions' => $submissions, 'summary' => $summary, 'period' => $period,
            'branches' => Branch::orderBy('name')->get(), 'filters' => $filters,
        ]);
    }

    public function create()
    {
        return $this->formView(new IncentiveSubmission(['transaction_date' => today(), 'currency' => 'IDR']));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $submission = DB::transaction(function () use ($request, $data) {
            $submission = IncentiveSubmission::create([
                ...collect($data)->only(['invoice_number', 'branch_id', 'transaction_date', 'currency', 'notes'])->all(),
                'submitted_by' => $request->user()->id, 'is_cooperation' => (bool) ($data['is_cooperation'] ?? false),
            ]);
            $this->replaceDetails($submission, $data);
            return $submission;
        });

        return redirect()->route('incentives.show', $submission)->with('ok', 'Pengajuan insentif tersimpan dan menunggu pemeriksaan CEO.');
    }

    public function show(Request $request, IncentiveSubmission $incentive)
    {
        $incentive = IncentiveSubmission::visibleTo($request->user())->with([
            'branch', 'submitter', 'decisionMaker', 'paymentChecker', 'items', 'participants.user', 'socialActions.beneficiary',
        ])->findOrFail($incentive->id);
        return view('incentives.show', compact('incentive'));
    }

    public function edit(Request $request, IncentiveSubmission $incentive)
    {
        abort_unless($request->user()->isCeo(), 403);
        $incentive->load(['items', 'participants', 'socialActions']);
        return $this->formView($incentive);
    }

    public function update(Request $request, IncentiveSubmission $incentive)
    {
        abort_unless($request->user()->isCeo(), 403);
        $data = $this->validated($request, $incentive);
        DB::transaction(function () use ($incentive, $data) {
            $incentive->update([
                ...collect($data)->only(['invoice_number', 'branch_id', 'transaction_date', 'currency', 'notes'])->all(),
                'is_cooperation' => (bool) ($data['is_cooperation'] ?? false),
            ]);
            $this->replaceDetails($incentive, $data);
        });
        return redirect()->route('incentives.show', $incentive)->with('ok', 'Data pengajuan berhasil diperbarui oleh CEO.');
    }

    public function decide(Request $request, IncentiveSubmission $incentive)
    {
        abort_unless($request->user()->isCeo(), 403);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected', 'pending'])],
            'rejection_reason' => ['nullable', 'string', 'max:2000', Rule::requiredIf($request->input('decision') === 'rejected')],
        ]);
        $incentive->update([
            'approval_status' => $data['decision'], 'rejection_reason' => $data['decision'] === 'rejected' ? $data['rejection_reason'] : null,
            'decision_by' => $data['decision'] === 'pending' ? null : $request->user()->id,
            'decision_at' => $data['decision'] === 'pending' ? null : now(),
        ]);
        return back()->with('ok', $data['decision'] === 'pending' ? 'Persetujuan dibatalkan dan dikembalikan ke antrean.' : 'Keputusan CEO berhasil disimpan.');
    }

    public function payment(Request $request, IncentiveSubmission $incentive)
    {
        abort_unless($request->user()->canReviewIncentivePayment(), 403);
        $data = $request->validate(['payment_status' => ['required', Rule::in(['paid', 'unpaid'])]]);
        $incentive->update([
            'payment_status' => $data['payment_status'], 'payment_checked_by' => $request->user()->id, 'payment_checked_at' => now(),
        ]);
        return back()->with('ok', 'Status pembayaran berhasil diperbarui Finance.');
    }

    private function formView(IncentiveSubmission $incentive)
    {
        $existingUserIds = $incentive->exists
            ? $incentive->participants->pluck('user_id')->merge($incentive->socialActions->pluck('beneficiary_id'))->unique()->all()
            : [];

        return view('incentives.form', [
            'incentive' => $incentive, 'branches' => Branch::orderBy('name')->get(),
            'employees' => User::with('branch')->where('is_active', true)
                ->where(fn ($q) => $q->whereNotNull('promotor_type')->when($existingUserIds, fn ($u) => $u->orWhereIn('id', $existingUserIds)))
                ->orderBy('name')->get(),
            'canOverridePromotorType' => auth()->user()->isCeo(),
        ]);
    }

    private function validated(Request $request, ?IncentiveSubmission $incentive = null): array
    {
        $existingUserIds = $incentive?->exists
            ? $incentive->participants()->pluck('user_id')->merge($incentive->socialActions()->pluck('beneficiary_id'))->unique()->all()
            : [];
        $eligiblePromotor = Rule::exists('users', 'id')->where(function ($query) use ($existingUserIds) {
            $query->where('is_active', true)
                ->where(fn ($user) => $user->whereNotNull('promotor_type')->when($existingUserIds, fn ($old) => $old->orWhereIn('id', $existingUserIds)));
        });

        $items = collect($request->input('items', []))->map(function ($item) {
            foreach (['capital_price', 'sale_price'] as $key) if (isset($item[$key])) $item[$key] = $this->normalizeMoney($item[$key]);
            return $item;
        })->all();
        $request->merge(['items' => $items]);
        $data = $request->validate([
            'invoice_number' => ['required', 'string', 'max:100', Rule::unique('incentive_submissions')->ignore($incentive?->id)],
            'branch_id' => ['required', 'integer', 'exists:branches,id'], 'transaction_date' => ['required', 'date', 'before_or_equal:today'],
            'currency' => ['required', Rule::in(array_keys(IncentiveSubmission::CURRENCIES))], 'notes' => ['nullable', 'string', 'max:2000'],
            'is_cooperation' => ['nullable', 'boolean'], 'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_name' => ['required', 'string', 'max:150'], 'items.*.ram' => ['nullable', 'string', 'max:50'],
            'items.*.color' => ['nullable', 'string', 'max:80'], 'items.*.condition' => ['required', Rule::in(array_keys(IncentiveSubmission::CONDITIONS))],
            'items.*.imei' => ['nullable', 'string', 'max:100'], 'items.*.capital_price' => ['required', 'numeric', 'min:0', 'max:99999999999999.99'],
            'items.*.sale_price' => ['required', 'numeric', 'min:0', 'max:99999999999999.99'],
            'participants' => ['required', 'array', 'min:1', 'max:20'], 'participants.*.user_id' => ['required', 'integer', 'distinct', $eligiblePromotor],
            'participants.*.incentive_type' => ['required', Rule::in(['fl', 'brand'])],
            'participants.*.cooperation_role' => ['nullable', Rule::in(['source', 'closing'])],
            'social_beneficiary_id' => ['nullable', 'integer', $eligiblePromotor],
            'social' => ['nullable', 'array'], 'social.*' => ['nullable', 'string', 'max:150'],
        ]);

        $promotorTypes = User::whereIn('id', collect($data['participants'])->pluck('user_id'))->pluck('promotor_type', 'id');
        if (! $request->user()->isCeo()) {
            foreach ($data['participants'] as &$participant) {
                $participant['incentive_type'] = $promotorTypes[(int) $participant['user_id']];
            }
            unset($participant);
        }

        $participants = collect($data['participants']);
        if (! empty($data['is_cooperation'])) {
            if ($participants->count() !== 2 || $participants->pluck('cooperation_role')->sort()->values()->all() !== ['closing', 'source']) {
                throw ValidationException::withMessages(['participants' => 'Kerja sama wajib tepat 2 promotor: satu pencari customer dan satu penyelesai transaksi.']);
            }
        }
        $social = collect($data['social'] ?? [])->filter(fn ($name) => filled($name));
        if ($social->isNotEmpty() && empty($data['social_beneficiary_id'])) {
            throw ValidationException::withMessages(['social_beneficiary_id' => 'Pilih pegawai penerima insentif rating/follow.']);
        }
        return $data;
    }

    private function replaceDetails(IncentiveSubmission $submission, array $data): void
    {
        $submission->items()->delete();
        $capital = $sales = 0;
        foreach ($data['items'] as $i => $item) {
            $submission->items()->create([...$item, 'sort_order' => $i]);
            $capital += (float) $item['capital_price']; $sales += (float) $item['sale_price'];
        }
        $submission->update(['total_capital' => $capital, 'total_sales' => $sales, 'total_profit' => $sales - $capital]);

        $submission->participants()->delete();
        $count = count($data['participants']);
        $used = 0.0;
        foreach (array_values($data['participants']) as $i => $participant) {
            $share = ! empty($data['is_cooperation']) ? 50.0 : ($i === $count - 1 ? 100 - $used : round(100 / $count, 2));
            $used += $share;
            $submission->participants()->create([...$participant, 'cooperation_role' => ! empty($data['is_cooperation']) ? $participant['cooperation_role'] : null, 'share_percent' => $share]);
        }

        $submission->socialActions()->delete();
        foreach (collect($data['social'] ?? [])->filter(fn ($name) => filled($name)) as $platform => $account) {
            if (! array_key_exists($platform, IncentiveSubmission::PLATFORMS)) continue;
            $submission->socialActions()->create(['beneficiary_id' => $data['social_beneficiary_id'], 'platform' => $platform, 'account_name' => trim($account)]);
        }
    }

    private function normalizeMoney(mixed $value): mixed
    {
        if (! is_string($value)) return $value;
        $value = trim(str_ireplace(['Rp', 'NT$', 'NTD', ' '], '', $value));
        return $value === '' ? $value : str_replace(',', '.', str_replace('.', '', $value));
    }
}
