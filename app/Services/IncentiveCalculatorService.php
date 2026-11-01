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
        $start = CarbonImmutable::createFromFormat('Y-m', $period)->startOfMonth();
        $end = $start->endOfMonth();
        $submissions = IncentiveSubmission::with(['participants.user', 'socialActions.beneficiary', 'branch'])
            ->where('approval_status', 'approved')->where('payment_status', 'paid')
            ->whereBetween('transaction_date', [$start->toDateString(), $end->toDateString()])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))->get();

        $groups = collect();
        foreach ($submissions as $submission) {
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

        $tiers = IncentiveTier::orderBy('min_target')->get()->groupBy(fn ($tier) => implode(':', [$tier->branch_id, $tier->currency, $tier->type]));

        return $groups->map(function (array $row) use ($tiers) {
            $key = implode(':', [$row['branch_id'], $row['currency'], $row['type']]);
            $tier = ($tiers->get($key) ?? collect())->filter(fn ($t) => (float) $t->min_target <= $row['metric'])->last();
            $row['reward_value'] = $tier ? (float) $tier->reward_value : 0;
            $row['tier_label'] = $tier?->label;
            $row['incentive'] = $row['type'] === 'social'
                ? $row['metric'] * $row['reward_value']
                : $row['metric'] * $row['reward_value'] / 100;
            return $row;
        })->sortBy(fn ($row) => $row['user']->name.'-'.$row['type'])->values();
    }

    private function blankRow($user, IncentiveSubmission $submission, string $type): array
    {
        return ['user' => $user, 'branch' => $submission->branch, 'branch_id' => $submission->branch_id,
            'currency' => $submission->currency, 'type' => $type, 'metric' => 0.0, 'transactions' => 0,
            'reward_value' => 0.0, 'tier_label' => null, 'incentive' => 0.0];
    }
}
