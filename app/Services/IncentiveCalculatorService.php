<?php

namespace App\Services;

use App\Models\IncentiveSubmission;
use App\Models\IncentiveTier;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class IncentiveCalculatorService
{
    public function calculate(string $period, ?int $branchId = null, ?int $userId = null): Collection
    {
        return $this->calculatePeriods([$period], $branchId, $userId)->get($period, collect());
    }

    /**
     * Hitung beberapa bulan sekaligus dengan satu query transaksi dan tier.
     * Masing-masing bulan tetap memakai snapshot target yang berlaku saat itu.
     *
     * @param  array<int, string>  $periods Format Y-m
     * @return Collection<string, Collection<int, array<string, mixed>>>
     */
    public function calculatePeriods(array $periods, ?int $branchId = null, ?int $userId = null): Collection
    {
        $periods = collect($periods)->filter()->unique()->sort()->values();
        if ($periods->isEmpty()) return collect();

        $bounds = $periods->mapWithKeys(function (string $period) {
            $start = CarbonImmutable::parse($period.'-01')->startOfMonth();
            return [$period => ['start' => $start, 'end' => $start->endOfMonth()]];
        });
        $first = $bounds->first()['start'];
        $last = $bounds->last()['end'];

        $submissions = IncentiveSubmission::with(['participants.user', 'socialActions.beneficiary', 'branch'])
            ->where('approval_status', 'approved')
            ->where('payment_status', 'paid')
            ->whereBetween('transaction_date', [$first->toDateString(), $last->toDateString()])
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->get();

        $groupsByPeriod = $periods->mapWithKeys(fn (string $period) => [$period => collect()]);
        foreach ($submissions as $submission) {
            $period = $submission->transaction_date->format('Y-m');
            if (! $groupsByPeriod->has($period)) continue;
            $groups = $groupsByPeriod->get($period);

            foreach ($submission->participants as $participant) {
                if ($userId && $participant->user_id !== $userId) continue;
                $key = implode(':', [$participant->user_id, $submission->branch_id, $submission->currency, $participant->incentive_type]);
                $metric = ($participant->incentive_type === 'fl' ? (float) $submission->total_profit : (float) $submission->total_sales)
                    * (float) $participant->share_percent / 100;
                $row = $groups->get($key, $this->blankRow($participant->user, $submission, $participant->incentive_type));
                $row['metric'] += $metric;
                $row['transactions']++;
                $groups->put($key, $row);
            }

            $social = $submission->socialActions->first();
            if ($social && (! $userId || $social->beneficiary_id === $userId)) {
                $key = implode(':', [$social->beneficiary_id, $submission->branch_id, $submission->currency, 'social']);
                $row = $groups->get($key, $this->blankRow($social->beneficiary, $submission, 'social'));
                $row['metric']++;
                $row['transactions']++;
                $groups->put($key, $row);
            }
        }

        $tierGroups = IncentiveTier::whereDate('effective_from', '<=', $bounds->last()['start']->toDateString())
            ->orderBy('effective_from')->orderBy('min_target')->get()
            ->groupBy(fn ($tier) => implode(':', [$tier->branch_id, $tier->currency, $tier->type]));

        return $periods->mapWithKeys(function (string $period) use ($groupsByPeriod, $tierGroups, $bounds) {
            $periodStart = $bounds->get($period)['start'];
            $activeTiers = $tierGroups->map(function (Collection $group) use ($periodStart) {
                $eligible = $group->filter(fn ($tier) => $tier->effective_from->lte($periodStart));
                $latest = $eligible->max(fn ($tier) => $tier->effective_from->format('Y-m-d'));
                return $latest
                    ? $eligible->filter(fn ($tier) => $tier->effective_from->format('Y-m-d') === $latest)->values()
                    : collect();
            });

            $rows = $groupsByPeriod->get($period)->map(fn (array $row) => $this->applyTier($row, $activeTiers))
                ->sortBy(fn ($row) => $row['user']->name.'-'.$row['type'])->values();

            return [$period => $rows];
        });
    }

    private function applyTier(array $row, Collection $tiers): array
    {
        $key = implode(':', [$row['branch_id'], $row['currency'], $row['type']]);
        $availableTiers = $tiers->get($key) ?? collect();
        $tier = $availableTiers->filter(fn ($candidate) => (float) $candidate->min_target <= $row['metric'])->last();
        $nextTier = $availableTiers->first(fn ($candidate) => (float) $candidate->min_target > $row['metric']);

        $row['reward_value'] = $tier ? (float) $tier->reward_value : 0;
        $row['tier_label'] = $tier?->label;
        $row['incentive'] = $row['type'] === 'social'
            ? $row['metric'] * $row['reward_value']
            : $row['metric'] * $row['reward_value'] / 100;
        $row['next_target'] = $nextTier ? (float) $nextTier->min_target : null;
        $row['next_reward_value'] = $nextTier ? (float) $nextTier->reward_value : null;
        $row['next_tier_label'] = $nextTier?->label;
        $row['remaining_to_next'] = $nextTier ? max(0, (float) $nextTier->min_target - $row['metric']) : 0.0;
        $row['next_incentive'] = $nextTier
            ? ($row['type'] === 'social'
                ? (float) $nextTier->min_target * (float) $nextTier->reward_value
                : (float) $nextTier->min_target * (float) $nextTier->reward_value / 100)
            : null;
        $row['progress_percent'] = $nextTier
            ? min(100.0, $row['metric'] / max(1, (float) $nextTier->min_target) * 100)
            : ($availableTiers->isNotEmpty() && $tier ? 100.0 : 0.0);
        $row['is_highest_tier'] = $availableTiers->isNotEmpty() && $tier && ! $nextTier;

        return $row;
    }

    private function blankRow($user, IncentiveSubmission $submission, string $type): array
    {
        return [
            'user' => $user,
            'branch' => $submission->branch,
            'branch_id' => $submission->branch_id,
            'currency' => $submission->currency,
            'type' => $type,
            'metric' => 0.0,
            'transactions' => 0,
            'reward_value' => 0.0,
            'tier_label' => null,
            'incentive' => 0.0,
        ];
    }
}
