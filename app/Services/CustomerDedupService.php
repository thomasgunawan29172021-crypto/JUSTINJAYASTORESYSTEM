<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Support\Collection;

/**
 * Cari kemungkinan pelanggan yang sama pas nomor HP-nya beda (keputusan #8).
 * SENGAJA cuma nge-flag, gak auto-merge/auto-tolak — staff yang mutusin final,
 * karena nama+alamat mirip bukan bukti pasti orang yang sama.
 */
class CustomerDedupService
{
    /** Ambang similar_text() buat nama dianggap "mirip". Tuning manual kalau kebanyakan/kekurangan false positive. */
    private const NAME_SIMILARITY_THRESHOLD = 70;

    public function findPossibleDuplicates(string $name, ?string $phone, ?string $address): Collection
    {
        $matches = collect();

        // 1) Match KERAS — nomor kontak sama persis, di customer manapun.
        if ($phone) {
            $normalized = Customer::normalizePhone($phone);

            Customer::whereHas('contacts', fn ($q) => $q
                ->whereIn('type', ['phone', 'whatsapp'])
                ->where('value', $normalized))
                ->get()
                ->each(fn ($c) => $matches->put($c->id, [
                    'customer' => $c, 'reason' => 'Nomor kontak sama persis', 'score' => 100,
                ]));
        }

        // 2) Match LUNAK — nama mirip (+ alamat mirip kalau ada), fallback #8.
        $needle = $this->normalizeName($name);

        Customer::query()
            ->when($matches->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $matches->keys()))
            ->get(['id', 'name', 'address', 'branch_id'])
            ->each(function ($c) use ($needle, $address, $matches) {
                similar_text($needle, $this->normalizeName($c->name), $percent);

                if ($percent >= self::NAME_SIMILARITY_THRESHOLD) {
                    $addrNote = ($address && $c->address) ? $this->addressNote($address, $c->address) : '';
                    $matches->put($c->id, [
                        'customer' => $c,
                        'reason'   => 'Nama mirip ('.round($percent).'%)'.$addrNote,
                        'score'    => (int) $percent,
                    ]);
                }
            });

        return $matches->sortByDesc('score')->values();
    }

    private function normalizeName(string $name): string
    {
        return strtolower(preg_replace('/[^a-z0-9 ]/i', '', trim($name)));
    }

    private function addressNote(string $a, string $b): string
    {
        similar_text(strtolower($a), strtolower($b), $percent);

        return $percent >= 60 ? ', alamat juga mirip' : '';
    }
}