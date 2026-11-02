<?php

namespace App\Services;

use App\Models\IncentiveSubmission;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class IncentiveMotivationService
{
    public const MARKETS = [
        'IDR' => ['key' => 'indonesia', 'label' => 'Indonesia', 'flag' => '🇮🇩'],
        'NTD' => ['key' => 'taiwan', 'label' => 'Taiwan', 'flag' => '🇹🇼'],
    ];

    public function __construct(private IncentiveCalculatorService $calculator) {}

    public function dashboard(string $period, ?int $branchId, User $viewer): array
    {
        $start = CarbonImmutable::parse($period.'-01')->startOfMonth();
        $end = $start->endOfMonth();
        $previousStart = $start->subMonth()->startOfMonth();

        $yearPeriods = collect();
        for ($cursor = $start->startOfYear(); $cursor->lte($start); $cursor = $cursor->addMonth()) {
            $yearPeriods->push($cursor->format('Y-m'));
        }
        $allPeriods = $yearPeriods->concat([$previousStart->format('Y-m')])->unique()->values();
        $calculated = $this->calculator->calculatePeriods($allPeriods->all(), $branchId);
        $currentRows = $calculated->get($period, collect());
        $previousRows = $calculated->get($previousStart->format('Y-m'), collect());

        $current = $this->aggregate(
            $currentRows,
            $this->transactionCounts($start, $end, $branchId),
            true
        );
        $previous = $this->aggregate(
            $previousRows,
            $this->transactionCounts($previousStart, $previousStart->endOfMonth(), $branchId)
        )->keyBy('key');

        $yearRows = $yearPeriods->flatMap(fn (string $yearPeriod) => $calculated->get($yearPeriod, collect()));
        $year = $this->aggregate(
            $yearRows,
            $this->transactionCounts($start->startOfYear(), $end, $branchId)
        );
        $yearByKey = $year->keyBy('key');

        $current = $current->map(function (array $entry) use ($previous, $yearByKey) {
            $previousIncentive = (float) ($previous->get($entry['key'])['incentive'] ?? 0);
            $entry['previous_incentive'] = $previousIncentive;
            $entry['comparison_percent'] = $previousIncentive > 0
                ? (($entry['incentive'] - $previousIncentive) / $previousIncentive) * 100
                : null;
            $entry['year_incentive'] = (float) ($yearByKey->get($entry['key'])['incentive'] ?? 0);
            $entry['year_transactions'] = (int) ($yearByKey->get($entry['key'])['transactions'] ?? 0);

            return $entry;
        });

        $personal = $current->where('user_id', $viewer->id)->values();

        return [
            'period' => $period,
            'year' => $start->year,
            'personal' => $personal,
            'message' => $this->messageFor($viewer, $personal),
            'leaderboards' => collect(self::MARKETS)->map(function (array $market, string $currency) use ($current, $year) {
                return [
                    ...$market,
                    'currency' => $currency,
                    'monthly' => $this->rank($current->where('currency', $currency)),
                    'yearly' => $this->rank($year->where('currency', $currency)),
                ];
            })->values(),
        ];
    }

    public function loginMessage(User $user): ?array
    {
        $start = CarbonImmutable::now()->startOfMonth();
        $rows = $this->calculator->calculate($start->format('Y-m'));
        $personal = $this->aggregate(
            $rows,
            $this->transactionCounts($start, $start->endOfMonth(), null),
            true
        )->where('user_id', $user->id)->values();
        if ($personal->isEmpty()) return null;

        return [
            'title' => 'Selamat & terima kasih, '.$user->name.'! 🎉',
            'message' => $this->messageFor($user, $personal),
            'url' => route('incentives.index'),
        ];
    }

    private function aggregate(Collection $rows, Collection $transactionCounts, bool $includeGoals = false): Collection
    {
        $entries = collect();

        foreach ($rows as $row) {
            $key = $row['user']->id.':'.$row['currency'];
            $entry = $entries->get($key, [
                'key' => $key,
                'user' => $row['user'],
                'user_id' => $row['user']->id,
                'currency' => $row['currency'],
                'market' => self::MARKETS[$row['currency']]['label'] ?? $row['currency'],
                'incentive' => 0.0,
                'transactions' => 0,
                'goals' => collect(),
            ]);
            $entry['incentive'] += (float) $row['incentive'];

            if ($includeGoals && ($row['next_target'] !== null || $row['is_highest_tier'])) {
                $entry['goals']->push($row);
            }

            $entries->put($key, $entry);
        }

        return $entries->map(function (array $entry) use ($transactionCounts) {
            $entry['transactions'] = (int) ($transactionCounts->get($entry['key']) ?? 0);
            $entry['goals'] = $entry['goals']->sortByDesc('progress_percent')->values();

            return $entry;
        })->values();
    }

    private function transactionCounts(CarbonImmutable $start, CarbonImmutable $end, ?int $branchId): Collection
    {
        $counts = collect();
        $submissions = IncentiveSubmission::with(['participants:id,incentive_submission_id,user_id', 'socialActions:id,incentive_submission_id,beneficiary_id'])
            ->where('approval_status', 'approved')
            ->where('payment_status', 'paid')
            ->whereBetween('transaction_date', [$start->toDateString(), $end->toDateString()])
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->get(['id', 'currency']);

        foreach ($submissions as $submission) {
            $userIds = $submission->participants->pluck('user_id')
                ->merge($submission->socialActions->pluck('beneficiary_id'))
                ->unique();
            foreach ($userIds as $userId) {
                $key = $userId.':'.$submission->currency;
                $counts->put($key, (int) $counts->get($key, 0) + 1);
            }
        }

        return $counts;
    }

    private function rank(Collection $entries): Collection
    {
        return $entries->sort(function (array $a, array $b) {
            return $b['incentive'] <=> $a['incentive']
                ?: $b['transactions'] <=> $a['transactions']
                ?: strcasecmp($a['user']->name, $b['user']->name);
        })->values()->map(function (array $entry, int $index) {
            $entry['rank'] = $index + 1;
            return $entry;
        });
    }

    private function messageFor(User $user, Collection $personal): string
    {
        if ($personal->isEmpty()) {
            return 'Terima kasih sudah menjadi bagian dari tim. Mulai langkah pertamamu bulan ini dan kejar pencapaian terbaikmu!';
        }

        $totals = $personal->map(fn (array $entry) => $this->money($entry['incentive'], $entry['currency']))->join(' + ');
        $bestGoal = $personal->pluck('goals')->flatten(1)
            ->whereNotNull('next_target')->sortByDesc('progress_percent')->first();
        $message = "Kerja hebat, {$user->name}! Pencapaianmu menghasilkan insentif sementara {$totals}. Terima kasih atas semangat dan kontribusimu untuk tim.";

        if ($bestGoal) {
            $remaining = $bestGoal['type'] === 'social'
                ? number_format($bestGoal['remaining_to_next'], 0, ',', '.').' orang'
                : $this->money($bestGoal['remaining_to_next'], $bestGoal['currency']);
            $target = $bestGoal['type'] === 'social'
                ? number_format($bestGoal['next_target'], 0, ',', '.').' orang'
                : $this->money($bestGoal['next_target'], $bestGoal['currency']);
            $potential = $this->money($bestGoal['next_incentive'], $bestGoal['currency']);
            $message .= " Tinggal {$remaining} lagi menuju target {$target} dengan potensi insentif {$potential}. Ayo, sedikit lagi!";
        } elseif ($personal->pluck('goals')->flatten(1)->contains('is_highest_tier', true)) {
            $message .= ' Selamat, kamu sudah mencapai tingkat tertinggi. Pertahankan performa luar biasa ini!';
        }

        return $message;
    }

    private function money(float $value, string $currency): string
    {
        return ($currency === 'IDR' ? 'Rp ' : 'NT$ ').number_format($value, 0, ',', '.');
    }
}
