<?php

namespace App\Http\Controllers\Crm;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Purchase;
use App\Models\Reminder;
use App\Models\WaitingItem;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SalesController extends Controller
{
    public function index(Request $request)
    {
        $request->merge([
            'min_total' => $this->normalizeCurrency($request->input('min_total')),
            'max_total' => $this->normalizeCurrency($request->input('max_total')),
        ]);
        $filters = $request->validate([
            'q' => 'nullable|string|max:150', 'date_from' => 'nullable|date', 'date_to' => 'nullable|date',
            'brand' => 'nullable|string|max:100', 'product_type' => 'nullable|string|max:100',
            'payment_method' => ['nullable', Rule::in(array_keys(Purchase::PAYMENT_METHODS))],
            'source' => 'nullable|string|max:50', 'domicile' => 'nullable|string|max:100',
            'min_total' => 'nullable|numeric|min:0', 'max_total' => 'nullable|numeric|min:0',
            'branch_id' => 'nullable|integer|exists:branches,id',
        ]);

        $base = Purchase::visibleTo($request->user())->with(['customer.contacts', 'branch', 'items', 'creator']);
        $this->applyFilters($base, $filters, $request);

        $summaryQuery = clone $base;
        $summary = [
            'transactions' => (clone $summaryQuery)->count(),
            'units' => (int) (clone $summaryQuery)->join('purchase_items', 'purchases.id', '=', 'purchase_items.purchase_id')->sum('purchase_items.quantity'),
            'revenue' => (float) (clone $summaryQuery)->sum('total_amount'),
            'customers' => (clone $summaryQuery)->distinct()->count('customer_id'),
        ];
        $purchases = $base->latest('purchased_at')->latest('id')->paginate(20)->withQueryString();

        $options = [
            'brands' => \App\Models\PurchaseItem::whereHas('purchase', fn ($q) => $q->visibleTo($request->user()))->whereNotNull('brand')->where('brand', '!=', '')->distinct()->orderBy('brand')->pluck('brand'),
            'types' => \App\Models\PurchaseItem::whereHas('purchase', fn ($q) => $q->visibleTo($request->user()))->whereNotNull('product_type')->where('product_type', '!=', '')->distinct()->orderBy('product_type')->pluck('product_type'),
            'sources' => Customer::whereHas('purchases', fn ($q) => $q->visibleTo($request->user()))->whereNotNull('source')->where('source', '!=', '')->distinct()->orderBy('source')->pluck('source'),
            'branches' => $this->branches($request),
        ];

        return view('crm.sales.index', compact('purchases', 'summary', 'options'));
    }

    public function create(Request $request)
    {
        $customer = null;
        if ($request->filled('customer_id')) {
            $customer = $this->customers($request)->with('contacts')->find($request->integer('customer_id'));
        }

        return view('crm.sales.form', ['purchase' => null, 'customer' => $customer, 'branches' => $this->branches($request)]);
    }

    public function lookupCustomer(Request $request): JsonResponse
    {
        $data = $request->validate(['phone' => ['required', 'string', 'max:30', 'regex:/^[+0-9()\s-]+$/']]);
        $phone = $this->validPhone($data['phone']);
        $matches = $this->customers($request)->with('contacts')->whereHas('contacts', fn ($q) => $q->whereIn('type', ['phone', 'whatsapp'])->where('value', $phone))->limit(10)->get();

        return response()->json(['normalized' => $phone, 'customers' => $matches->map(fn ($customer) => [
            'id' => $customer->id, 'name' => $customer->name, 'address' => $customer->address,
            'city' => $customer->city, 'source' => $customer->source, 'branch_id' => $customer->branch_id,
            'phones' => $customer->contacts->whereIn('type', ['phone', 'whatsapp'])->pluck('value')->values(),
        ])->values()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $purchase = DB::transaction(function () use ($request, $data) {
            $customer = $this->resolveCustomer($request, $data);
            $branchId = $this->branchId($request, $data['branch_id']);
            $purchase = Purchase::create([
                'customer_id' => $customer->id, 'branch_id' => $branchId, 'created_by' => $request->user()->id,
                'purchased_at' => $data['purchased_at'], 'payment_method' => $data['payment_method'],
                'total_amount' => 0, 'notes' => $data['notes'] ?? null,
            ]);
            $this->replaceItems($purchase, $data['items']);
            $this->replaceReminders($purchase);
            $this->completeMatchingWaitingItems($purchase, $request);
            $customer->histories()->create(['user_id' => $request->user()->id, 'action' => 'purchase_created', 'note' => 'Penjualan #'.$purchase->id.' dicatat', 'created_at' => now()]);

            return $purchase;
        });

        return redirect()->route('crm.sales.show', $purchase)->with('ok', 'Penjualan berhasil dicatat dan jadwal follow-up dibuat.');
    }

    public function show(Request $request, Purchase $purchase)
    {
        $purchase = Purchase::visibleTo($request->user())->with(['customer.contacts', 'branch', 'items', 'creator', 'reminders.completer'])->findOrFail($purchase->id);

        return view('crm.sales.show', compact('purchase'));
    }

    public function edit(Request $request, Purchase $purchase)
    {
        abort_unless($request->user()->isCeo(), 403);
        $purchase->load(['customer.contacts', 'items']);

        return view('crm.sales.form', ['purchase' => $purchase, 'customer' => $purchase->customer, 'branches' => Branch::orderBy('name')->get()]);
    }

    public function update(Request $request, Purchase $purchase)
    {
        abort_unless($request->user()->isCeo(), 403);
        $data = $this->validated($request, true);
        DB::transaction(function () use ($purchase, $request, $data) {
            $customer = $this->resolveCustomer($request, $data);
            $purchase->update([
                'customer_id' => $customer->id, 'branch_id' => $data['branch_id'], 'purchased_at' => $data['purchased_at'],
                'payment_method' => $data['payment_method'], 'notes' => $data['notes'] ?? null,
            ]);
            $this->replaceItems($purchase, $data['items']);
            $this->replaceReminders($purchase);
            $customer->histories()->create(['user_id' => $request->user()->id, 'action' => 'purchase_updated', 'note' => 'Penjualan #'.$purchase->id.' diperbarui', 'created_at' => now()]);
        });

        return redirect()->route('crm.sales.show', $purchase)->with('ok', 'Penjualan diperbarui.');
    }

    public function destroy(Request $request, Purchase $purchase)
    {
        abort_unless($request->user()->isCeo(), 403);
        DB::transaction(function () use ($purchase, $request) {
            $purchase->customer->histories()->create(['user_id' => $request->user()->id, 'action' => 'purchase_deleted', 'note' => 'Penjualan #'.$purchase->id.' dihapus', 'created_at' => now()]);
            $purchase->reminders()->delete();
            $purchase->delete();
        });

        return redirect()->route('crm.sales.index')->with('ok', 'Penjualan dihapus.');
    }

    private function validated(Request $request, bool $editing = false): array
    {
        $request->merge(['items' => collect($request->input('items', []))->map(function ($item) {
            if (is_array($item) && array_key_exists('unit_price', $item)) $item['unit_price'] = $this->normalizeCurrency($item['unit_price']);
            return $item;
        })->all()]);

        return $request->validate([
            'phone' => ['required', 'string', 'max:30', 'regex:/^[+0-9()\s-]+$/'],
            'customer_id' => 'nullable|integer|min:1', 'name' => 'nullable|string|max:100',
            'secondary_phone' => ['nullable', 'string', 'max:30', 'regex:/^[+0-9()\s-]+$/'],
            'address' => 'nullable|string|max:255', 'city' => 'nullable|string|max:100', 'source' => 'nullable|string|max:50',
            'branch_id' => 'required|integer|exists:branches,id', 'purchased_at' => 'required|date|before_or_equal:today',
            'payment_method' => ['required', Rule::in(array_keys(Purchase::PAYMENT_METHODS))], 'notes' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1|max:100', 'items.*.product_name' => 'required|string|max:150',
            'items.*.brand' => 'nullable|string|max:100', 'items.*.product_type' => 'nullable|string|max:100',
            'items.*.quantity' => 'required|integer|min:1|max:9999',
            'items.*.unit_price' => 'required|numeric|gt:0|max:999999999999.99|decimal:0,2',
        ]);
    }

    private function resolveCustomer(Request $request, array $data): Customer
    {
        $phone = $this->validPhone($data['phone']);
        $matches = $this->customers($request)->whereHas('contacts', fn ($q) => $q->whereIn('type', ['phone', 'whatsapp'])->where('value', $phone))->get();
        if (! empty($data['customer_id'])) {
            $customer = $matches->firstWhere('id', (int) $data['customer_id']);
            if (! $customer) throw ValidationException::withMessages(['phone' => 'Nomor tidak cocok dengan pelanggan yang dipilih. Cek nomor sekali lagi.']);
            return $customer;
        }
        if ($matches->count() === 1) return $matches->first();
        if ($matches->count() > 1) throw ValidationException::withMessages(['customer_id' => 'Nomor dipakai beberapa pelanggan. Pilih nama pelanggan dari hasil pencarian.']);
        if (Customer::withTrashed()->whereHas('contacts', fn ($q) => $q->whereIn('type', ['phone', 'whatsapp'])->where('value', $phone))->exists()) {
            throw ValidationException::withMessages(['phone' => 'Nomor sudah terdaftar di cabang lain atau pada data yang dihapus. Hubungi CRM untuk memakai data pelanggan tersebut.']);
        }
        if (blank($data['name'] ?? null)) throw ValidationException::withMessages(['name' => 'Nama wajib diisi untuk pelanggan baru.']);

        $contacts = [['type' => 'phone', 'value' => $phone, 'is_primary' => true]];
        if (! blank($data['secondary_phone'] ?? null)) {
            $secondary = $this->validPhone($data['secondary_phone']);
            if ($secondary === $phone) throw ValidationException::withMessages(['secondary_phone' => 'Nomor kedua harus berbeda.']);
            if (Customer::withTrashed()->whereHas('contacts', fn ($q) => $q->whereIn('type', ['phone', 'whatsapp'])->where('value', $secondary))->exists()) {
                throw ValidationException::withMessages(['secondary_phone' => 'Nomor kedua sudah terdaftar pada pelanggan lain.']);
            }
            $contacts[] = ['type' => 'phone', 'value' => $secondary, 'is_primary' => false];
        }
        return Customer::register([
            'name' => trim($data['name']), 'address' => $data['address'] ?? null, 'city' => $data['city'] ?? null, 'source' => $data['source'] ?? null,
            'branch_id' => $this->branchId($request, $data['branch_id']),
        ], $contacts, $request->user());
    }

    private function replaceItems(Purchase $purchase, array $items): void
    {
        $purchase->items()->delete();
        $total = 0;
        foreach ($items as $item) {
            $name = preg_replace('/\s+/u', ' ', trim($item['product_name']));
            $subtotal = (float) $item['unit_price'] * (int) $item['quantity'];
            $purchase->items()->create([...$item, 'product_name' => $name, 'product_key' => mb_strtolower($name), 'subtotal' => $subtotal]);
            $total += $subtotal;
        }
        $purchase->update(['total_amount' => $total]);
    }

    private function replaceReminders(Purchase $purchase): void
    {
        $purchase->reminders()->where('status', 'pending')->delete();
        $date = CarbonImmutable::parse($purchase->purchased_at);
        $dates = ['after_7_days' => $date->addDays(7), 'after_1_month' => $date->addMonthNoOverflow(), 'after_6_months' => $date->addMonthsNoOverflow(6), 'after_1_year' => $date->addYearNoOverflow()];
        foreach ($dates as $type => $scheduled) {
            $purchase->reminders()->updateOrCreate(['type' => $type], ['customer_id' => $purchase->customer_id, 'branch_id' => $purchase->branch_id, 'scheduled_at' => $scheduled->toDateString(), 'status' => 'pending', 'completed_by' => null, 'completed_at' => null, 'note' => null]);
        }
    }

    private function completeMatchingWaitingItems(Purchase $purchase, Request $request): void
    {
        $keys = $purchase->items()->pluck('product_key');
        WaitingItem::whereIn('product_key', $keys)->where('status', '!=', 'purchased')->whereHas('order', fn ($q) => $q->where('customer_id', $purchase->customer_id))->get()->each(function ($item) use ($request, $purchase) {
            $before = $item->status;
            $item->update(['status' => 'purchased', 'purchased_at' => now()]);
            $purchase->customer->histories()->create(['user_id' => $request->user()->id, 'action' => 'waiting_status', 'note' => 'Otomatis dari penjualan #'.$purchase->id.' / '.$item->product_name, 'changes' => ['before' => $before, 'after' => 'purchased'], 'created_at' => now()]);
        });
    }

    private function applyFilters($query, array $filters, Request $request): void
    {
        $query->when($filters['q'] ?? null, function ($q, $term) {
            $like = '%'.$term.'%';
            $q->where(fn ($s) => $s->whereHas('customer', fn ($c) => $c->where('name', 'like', $like)->orWhereHas('contacts', fn ($p) => $p->where('value', 'like', $like)))->orWhereHas('items', fn ($i) => $i->where('product_name', 'like', $like)));
        })->when($filters['date_from'] ?? null, fn ($q, $v) => $q->whereDate('purchased_at', '>=', $v))
            ->when($filters['date_to'] ?? null, fn ($q, $v) => $q->whereDate('purchased_at', '<=', $v))
            ->when($filters['brand'] ?? null, fn ($q, $v) => $q->whereHas('items', fn ($i) => $i->where('brand', $v)))
            ->when($filters['product_type'] ?? null, fn ($q, $v) => $q->whereHas('items', fn ($i) => $i->where('product_type', $v)))
            ->when($filters['payment_method'] ?? null, fn ($q, $v) => $q->where('payment_method', $v))
            ->when($filters['source'] ?? null, fn ($q, $v) => $q->whereHas('customer', fn ($c) => $c->where('source', $v)))
            ->when($filters['domicile'] ?? null, fn ($q, $v) => $q->whereHas('customer', fn ($c) => $c->where(fn ($area) => $area->where('city', 'like', '%'.$v.'%')->orWhere('address', 'like', '%'.$v.'%'))))
            ->when(isset($filters['min_total']), fn ($q) => $q->where('total_amount', '>=', $filters['min_total']))
            ->when(isset($filters['max_total']), fn ($q) => $q->where('total_amount', '<=', $filters['max_total']))
            ->when(($filters['branch_id'] ?? null) && ($request->user()->isCeo() || $request->user()->hasRole(UserRole::Crm)), fn ($q) => $q->where('branch_id', $filters['branch_id']));
    }

    private function customers(Request $request)
    {
        return Customer::query()->when(! $request->user()->isCeo() && ! $request->user()->hasRole(UserRole::Crm), fn ($q) => $q->where('branch_id', $request->user()->branch_id));
    }

    private function branches(Request $request)
    {
        return Branch::query()->when(! $request->user()->isCeo() && ! $request->user()->hasRole(UserRole::Crm), fn ($q) => $q->whereKey($request->user()->branch_id))->orderBy('name')->get();
    }

    private function branchId(Request $request, int|string $branchId): int
    {
        if (! $request->user()->isCeo() && ! $request->user()->hasRole(UserRole::Crm) && (int) $branchId !== (int) $request->user()->branch_id) abort(403);
        return (int) $branchId;
    }

    private function validPhone(string $phone): string
    {
        $phone = Customer::normalizePhone($phone);
        if (strlen($phone) < 9 || strlen($phone) > 15) throw ValidationException::withMessages(['phone' => 'Nomor telepon harus 9–15 digit.']);
        return $phone;
    }

    private function normalizeCurrency(mixed $value): mixed
    {
        if (! is_string($value)) return $value;
        $value = trim(str_replace(['Rp', 'rp', ' '], '', $value));
        return $value === '' ? $value : str_replace(',', '.', str_replace('.', '', $value));
    }
}
