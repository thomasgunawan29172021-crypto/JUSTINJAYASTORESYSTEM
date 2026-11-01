<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\IncentiveSubmission;
use App\Models\IncentiveTier;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class IncentiveSettingController extends Controller
{
    public function index(Request $request)
    {
        $effectiveDate = $this->nextEffectiveDate();
        $tiers = $this->configurationAt($effectiveDate);

        return view('incentives.settings', [
            'tiers' => $tiers,
            'branches' => Branch::orderBy('name')->get(),
            'editingTier' => $request->filled('edit') ? $tiers->firstWhere('id', $request->integer('edit')) : null,
            'effectiveDate' => $effectiveDate,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        DB::transaction(function () use ($data) {
            $effectiveDate = $this->nextEffectiveDate();
            $this->ensureSnapshot((int) $data['branch_id'], $data['currency'], $data['type'], $effectiveDate);
            IncentiveTier::updateOrCreate(
                collect($data)->only(['branch_id', 'currency', 'type', 'min_target'])->all() + ['effective_from' => $effectiveDate],
                collect($data)->only(['label', 'reward_value'])->all()
            );
        });

        return redirect()->route('incentives.settings')->with('ok', 'Target disimpan dan berlaku mulai bulan berikutnya.');
    }

    public function update(Request $request, IncentiveTier $tier)
    {
        $data = $this->validated($request);
        DB::transaction(function () use ($tier, $data) {
            $effectiveDate = $this->nextEffectiveDate();
            $this->ensureSnapshot($tier->branch_id, $tier->currency, $tier->type, $effectiveDate);

            $sameGroup = (int) $data['branch_id'] === (int) $tier->branch_id
                && $data['currency'] === $tier->currency && $data['type'] === $tier->type;
            $sourceCount = IncentiveTier::where('branch_id', $tier->branch_id)->where('currency', $tier->currency)
                ->where('type', $tier->type)->whereDate('effective_from', $effectiveDate)->count();
            if (! $sameGroup && $sourceCount <= 1) {
                throw ValidationException::withMessages(['type' => 'Baris terakhir dalam satu grup tidak dapat dipindahkan. Tambahkan target pengganti terlebih dahulu.']);
            }

            // Baris yang diedit selalu baris snapshot bulan depan. Riwayat lama
            // tidak pernah ditimpa agar perhitungan bulan sebelumnya tetap sama.
            IncentiveTier::where('branch_id', $tier->branch_id)
                ->where('currency', $tier->currency)
                ->where('type', $tier->type)
                ->whereDate('effective_from', $effectiveDate)
                ->where('min_target', $tier->min_target)
                ->delete();

            if (! $sameGroup) {
                $this->ensureSnapshot((int) $data['branch_id'], $data['currency'], $data['type'], $effectiveDate);
            }
            IncentiveTier::updateOrCreate(
                collect($data)->only(['branch_id', 'currency', 'type', 'min_target'])->all() + ['effective_from' => $effectiveDate],
                collect($data)->only(['label', 'reward_value'])->all()
            );
        });

        return redirect()->route('incentives.settings')->with('ok', 'Target diperbarui untuk bulan berikutnya.');
    }

    public function destroy(IncentiveTier $tier)
    {
        DB::transaction(function () use ($tier) {
            $effectiveDate = $this->nextEffectiveDate();
            $this->ensureSnapshot($tier->branch_id, $tier->currency, $tier->type, $effectiveDate);
            $target = IncentiveTier::where('branch_id', $tier->branch_id)
                ->where('currency', $tier->currency)
                ->where('type', $tier->type)
                ->whereDate('effective_from', $effectiveDate);
            if ((clone $target)->count() <= 1) {
                throw ValidationException::withMessages(['tier' => 'Minimal satu tingkat target harus tetap tersedia dalam setiap grup.']);
            }
            $target->where('min_target', $tier->min_target)->delete();
        });

        return redirect()->route('incentives.settings')->with('ok', 'Target dihapus untuk bulan berikutnya; riwayat lama tetap aman.');
    }

    public function copyToAll(Request $request)
    {
        $data = $request->validate([
            'source_branch_id' => ['required', 'integer', 'exists:branches,id'],
            'currency' => ['required', Rule::in(array_keys(IncentiveSubmission::CURRENCIES))],
            'type' => ['required', Rule::in(array_keys(IncentiveTier::TYPES))],
        ]);

        DB::transaction(function () use ($data) {
            $effectiveDate = $this->nextEffectiveDate();
            $this->ensureSnapshot((int) $data['source_branch_id'], $data['currency'], $data['type'], $effectiveDate);
            $source = IncentiveTier::where('branch_id', $data['source_branch_id'])
                ->where('currency', $data['currency'])->where('type', $data['type'])
                ->whereDate('effective_from', $effectiveDate)->orderBy('min_target')->get();

            foreach (Branch::where('id', '!=', $data['source_branch_id'])->pluck('id') as $branchId) {
                IncentiveTier::where('branch_id', $branchId)->where('currency', $data['currency'])
                    ->where('type', $data['type'])->whereDate('effective_from', $effectiveDate)->delete();
                foreach ($source as $tier) {
                    IncentiveTier::create([
                        'branch_id' => $branchId, 'currency' => $tier->currency, 'type' => $tier->type,
                        'effective_from' => $effectiveDate, 'label' => $tier->label,
                        'min_target' => $tier->min_target, 'reward_value' => $tier->reward_value,
                    ]);
                }
            }
        });

        return redirect()->route('incentives.settings')->with('ok', 'Pengaturan grup berhasil disalin ke semua cabang untuk bulan berikutnya.');
    }

    private function validated(Request $request): array
    {
        $request->merge([
            'min_target' => $this->normalizeMoney($request->input('min_target')),
            'reward_value' => $this->normalizeDecimal($request->input('reward_value')),
        ]);

        return $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'currency' => ['required', Rule::in(array_keys(IncentiveSubmission::CURRENCIES))],
            'type' => ['required', Rule::in(array_keys(IncentiveTier::TYPES))],
            'label' => ['nullable', 'string', 'max:80'],
            'min_target' => ['required', 'numeric', 'min:0'],
            'reward_value' => ['required', 'numeric', 'min:0'],
        ]);
    }

    private function nextEffectiveDate(): string
    {
        return now()->addMonthNoOverflow()->startOfMonth()->toDateString();
    }

    /** Ambil satu snapshot terbaru untuk tiap kombinasi cabang/mata uang/tipe. */
    private function configurationAt(string $date): Collection
    {
        return IncentiveTier::with('branch')->whereDate('effective_from', '<=', $date)
            ->orderBy('effective_from')->orderBy('min_target')->get()
            ->groupBy(fn ($tier) => implode('|', [$tier->branch_id, $tier->currency, $tier->type]))
            ->flatMap(function (Collection $group) {
                $latest = $group->max(fn ($tier) => $tier->effective_from->format('Y-m-d'));
                return $group->filter(fn ($tier) => $tier->effective_from->format('Y-m-d') === $latest);
            })->sortBy(fn ($tier) => sprintf('%08d|%s|%s|%020.2f', $tier->branch_id, $tier->currency, $tier->type, $tier->min_target))->values();
    }

    /** Buat snapshot lengkap bulan depan sebelum satu baris diubah/dihapus. */
    private function ensureSnapshot(int $branchId, string $currency, string $type, string $effectiveDate): void
    {
        if (IncentiveTier::where('branch_id', $branchId)->where('currency', $currency)->where('type', $type)
            ->whereDate('effective_from', $effectiveDate)->exists()) return;

        $sourceDate = IncentiveTier::where('branch_id', $branchId)->where('currency', $currency)->where('type', $type)
            ->whereDate('effective_from', '<', $effectiveDate)->max('effective_from');
        if (! $sourceDate) return;

        IncentiveTier::where('branch_id', $branchId)->where('currency', $currency)->where('type', $type)
            ->whereDate('effective_from', $sourceDate)->orderBy('min_target')->get()->each(function ($tier) use ($effectiveDate) {
                IncentiveTier::create([
                    'branch_id' => $tier->branch_id, 'currency' => $tier->currency, 'type' => $tier->type,
                    'effective_from' => $effectiveDate, 'label' => $tier->label,
                    'min_target' => $tier->min_target, 'reward_value' => $tier->reward_value,
                ]);
            });
    }

    private function normalizeMoney(mixed $value): mixed
    {
        if (! is_string($value)) return $value;
        $value = trim(str_ireplace(['Rp', 'NT$', 'NTD', ' '], '', $value));
        return $value === '' ? $value : str_replace(',', '.', str_replace('.', '', $value));
    }

    private function normalizeDecimal(mixed $value): mixed
    {
        if (! is_string($value)) return $value;
        $value = trim(str_replace(' ', '', $value));
        if ($value === '') return $value;
        return str_contains($value, ',') ? str_replace(',', '.', str_replace('.', '', $value)) : $value;
    }
}
