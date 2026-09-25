<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Customer extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'address', 'source', 'branch_id', 'notes'];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(CustomerContact::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(CustomerHistory::class)->latest('created_at');
    }

    public function primaryContact(): ?CustomerContact
    {
        return $this->contacts->firstWhere('is_primary', true) ?? $this->contacts->first();
    }

    /** Format 62xxx — kembaran ServiceTicket::normalizePhone(), biar konsisten satu app. */
    public static function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);

        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        } elseif (str_starts_with($digits, '8')) {
            $digits = '62'.$digits;
        }

        return $digits;
    }

    /**
     * Daftarin pelanggan baru + kontak-kontaknya + history awal, satu transaction.
     * $contacts: [['type' => 'phone', 'value' => '08123...', 'is_primary' => true], ...]
     */
    public static function register(array $attributes, array $contacts, User $creator): self
    {
        return DB::transaction(function () use ($attributes, $contacts, $creator) {
            $customer = new self($attributes);
            $customer->created_by = $creator->id;
            $customer->save();

            foreach ($contacts as $c) {
                if (in_array($c['type'], ['phone', 'whatsapp'], true)) {
                    $c['value'] = self::normalizePhone($c['value']);
                }
                $customer->contacts()->create($c);
            }

            $customer->histories()->create([
                'user_id'    => $creator->id,
                'action'     => 'created',
                'note'       => 'Pelanggan baru didaftarkan',
                'created_at' => now(),
            ]);

            return $customer;
        });
    }

    /** Update data inti + catat diff ke history. $attributes cuma field customers, bukan contacts. */
    public function updateInfo(array $attributes, User $editor): void
    {
        DB::transaction(function () use ($attributes, $editor) {
            $before = $this->only(array_keys($attributes));
            $this->fill($attributes)->save();

            $this->histories()->create([
                'user_id'    => $editor->id,
                'action'     => 'updated',
                'changes'    => ['before' => $before, 'after' => $attributes], // langsung pakai $attributes
                'created_at' => now(),
            ]);
        });
    }
}