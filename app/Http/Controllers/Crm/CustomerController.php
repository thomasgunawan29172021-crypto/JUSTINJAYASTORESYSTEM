<?php

namespace App\Http\Controllers\Crm;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Reminder;
use App\Services\CustomerDedupService;
use App\Services\CustomerExcelExport;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function __construct(private CustomerDedupService $dedup, private CustomerExcelExport $excel) {}

    public function index(Request $request)
    {
        $filters = $this->filters($request);
        $relations = ['contacts', 'branch'];
        if ($request->user()->canManageSales()) $relations[] = 'latestPurchase.items';
        $customerQuery = $this->customerQuery($request, $filters)->with($relations);
        if ($request->user()->canManageSales()) $customerQuery->withCount('purchases');
        $customers = $customerQuery->paginate(20)->withQueryString();

        $optionCustomers = Customer::query()->when(! $request->user()->isCeo() && ! $request->user()->hasRole(UserRole::Crm), fn ($q) => $q->where('branch_id', $request->user()->branch_id));
        $options = [
            'brands' => PurchaseItem::whereHas('purchase', fn ($q) => $q->visibleTo($request->user()))->whereNotNull('brand')->where('brand', '!=', '')->distinct()->orderBy('brand')->pluck('brand'),
            'sources' => (clone $optionCustomers)->whereNotNull('source')->where('source', '!=', '')->distinct()->orderBy('source')->pluck('source'),
            'cities' => (clone $optionCustomers)->whereNotNull('city')->where('city', '!=', '')->distinct()->orderBy('city')->pluck('city'),
            'branches' => Branch::query()->when(! $request->user()->isCeo() && ! $request->user()->hasRole(UserRole::Crm), fn ($q) => $q->whereKey($request->user()->branch_id))->orderBy('name')->get(),
        ];

        return view('crm.customers.index', compact('customers', 'options', 'filters'));
    }

    public function export(Request $request)
    {
        abort_unless($request->user()->canManageSales(), 403);
        $filters = $this->filters($request);
        $customers = $this->customerQuery($request, $filters)->with(['contacts', 'branch', 'latestPurchase.items'])->withCount('purchases')->get();
        $contents = $this->excel->render($customers);

        return response($contents, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="pelanggan-crm-'.now()->format('Y-m-d-His').'.xlsx"',
        ]);
    }

    public function create(Request $request)
    {
        $user = $request->user();
        $branches = Branch::all();
        $duplicates = collect();

        if ($request->session()->has('dedup_preview')) {
            $duplicates = collect($request->session()->pull('dedup_preview'));
        }

        return view('crm.customers.create', compact('branches', 'duplicates', 'user'));
    }

    public function store(Request $request)
    {
        $request->merge(['purchase_unit_price' => $this->normalizeCurrency($request->input('purchase_unit_price'))]);
        $data = $request->validate([
            'name'          => ['required', 'string', 'max:100'],
            'address'       => ['nullable', 'string', 'max:255'],
            'city'          => ['nullable', 'string', 'max:100'],
            'source'        => ['nullable', 'string', 'max:50'],
            'source_other'  => ['nullable', 'string', 'max:50'],
            'branch_id'     => ['required', 'exists:branches,id'],
            'notes'         => ['nullable', 'string'],
            'contact_type'  => ['required', 'array', 'min:1'],
            'contact_type.*' => ['in:phone,whatsapp,email,other'],
            'contact_value'  => ['required', 'array', 'min:1'],
            'contact_value.*' => ['required', 'string', 'max:150'],
            'contact_primary' => ['nullable', 'integer'], // index kontak yang dijadiin primary
            'confirm_create'  => ['nullable', 'boolean'],
            'purchase_product_name' => ['nullable', 'string', 'max:150'],
            'purchase_brand' => ['required_with:purchase_product_name', 'nullable', 'string', 'max:100'],
            'purchase_product_type' => ['required_with:purchase_product_name', 'nullable', 'string', 'max:100'],
            'purchase_unit_price' => ['required_with:purchase_product_name', 'nullable', 'numeric', 'gt:0', 'max:999999999999.99'],
            'purchase_payment_method' => ['required_with:purchase_product_name', 'nullable', Rule::in(array_keys(Purchase::PAYMENT_METHODS))],
            'purchase_date' => ['required_with:purchase_product_name', 'nullable', 'date', 'before_or_equal:today'],
        ]);

        if (($data['source'] ?? null) === 'lainnya') {
            $data['source'] = $data['source_other'] ?: 'lainnya';
        }

        if (! $request->user()->isCeo() && ! $request->user()->hasRole(\App\Enums\UserRole::Crm)) {
            $data['branch_id'] = $request->user()->branch_id;
        }

        // Cek dedup pakai nama + kontak phone/whatsapp pertama yang diisi + alamat.
        $phoneForCheck = null;
        foreach ($data['contact_type'] as $i => $type) {
            if (in_array($type, ['phone', 'whatsapp'], true)) {
                $phoneForCheck = $data['contact_value'][$i];
                break;
            }
        }

        if (empty($data['confirm_create'])) {
            $duplicates = $this->dedup->findPossibleDuplicates($data['name'], $phoneForCheck, $data['address'] ?? null);

            if ($duplicates->isNotEmpty()) {
                return back()->withInput()->with('dedup_preview', $duplicates->map(fn ($m) => [
                    'id'     => $m['customer']->id,
                    'name'   => $m['customer']->name,
                    'reason' => $m['reason'],
                    'score'  => $m['score'],
                ])->all());
            }
        }

        $contacts = [];
        foreach ($data['contact_type'] as $i => $type) {
            $contacts[] = [
                'type'       => $type,
                'value'      => $data['contact_value'][$i],
                'is_primary' => (int) ($data['contact_primary'] ?? 0) === $i,
            ];
        }
        if (! collect($contacts)->contains('is_primary', true)) {
            $contacts[0]['is_primary'] = true; // fallback: kontak pertama jadi primary
        }

        $customer = DB::transaction(function () use ($data, $contacts, $request) {
            $customer = Customer::register(
                collect($data)->only(['name', 'address', 'city', 'source', 'branch_id', 'notes'])->all(),
                $contacts,
                $request->user(),
            );

            if ($request->user()->canManageSales() && filled($data['purchase_product_name'] ?? null)) {
                $this->createInitialPurchase($customer, $data, $request);
            }

            return $customer;
        });

        return redirect()->route('crm.customers.show', $customer)
            ->with('ok', "Pelanggan {$customer->name} berhasil didaftarkan.");
    }

    public function show(Request $request, Customer $customer)
    {
        $this->authorizeCustomer($request, $customer);
        $relations = ['contacts', 'branch', 'creator', 'histories.user'];
        if ($request->user()->canManageSales()) {
            $relations['purchases'] = fn ($q) => $q->latest('purchased_at')->with('items');
            $relations['reminders'] = fn ($q) => $q->latest('scheduled_at')->with('completer');
        }
        $customer->load($relations);

        return view('crm.customers.show', compact('customer'));
    }

    public function edit(Request $request, Customer $customer)
    {
        $this->authorizeCustomer($request, $customer);
        $branches = Branch::query()->when(! $request->user()->isCeo() && ! $request->user()->hasRole(\App\Enums\UserRole::Crm), fn ($q) => $q->whereKey($request->user()->branch_id))->get();
        $user = $request->user();

        return view('crm.customers.edit', compact('customer', 'branches', 'user'));
    }
    
    public function update(Request $request, Customer $customer)
    {
        $this->authorizeCustomer($request, $customer);
        $data = $request->validate([
            'name'      => ['required', 'string', 'max:100'],
            'address'   => ['nullable', 'string', 'max:255'],
            'city'      => ['nullable', 'string', 'max:100'],
            'source'    => ['nullable', 'string', 'max:50'],
            'source_other' => ['nullable', 'string', 'max:50'],
            'branch_id' => ['required', 'exists:branches,id'],
            'notes'     => ['nullable', 'string'],
        ]);

        if (($data['source'] ?? null) === 'lainnya') {
            $data['source'] = $data['source_other'] ?: 'lainnya';
        }
        unset($data['source_other']);

        if (! $request->user()->isCeo() && ! $request->user()->hasRole(\App\Enums\UserRole::Crm)) {
            $data['branch_id'] = $request->user()->branch_id;
        }

        $customer->updateInfo($data, $request->user());

        return redirect()->route('crm.customers.show', $customer)->with('ok', 'Data pelanggan diperbarui.');
    }

    public function destroy(Request $request, Customer $customer)
    {
        $this->authorizeCustomer($request, $customer);
        $customer->histories()->create([
            'user_id'    => $request->user()->id,
            'action'     => 'deleted',
            'created_at' => now(),
        ]);
        $customer->delete(); // soft delete

        return redirect()->route('crm.customers.index')->with('ok', 'Pelanggan dihapus (masih bisa dipulihkan lewat trash).');
    }

    private function authorizeCustomer(Request $request, Customer $customer): void
    {
        abort_unless($request->user()->isCeo() || $request->user()->hasRole(\App\Enums\UserRole::Crm) || $customer->branch_id === $request->user()->branch_id, 403);
    }

    private function filters(Request $request): array
    {
        $request->merge([
            'min_price' => $this->normalizeCurrency($request->input('min_price')),
            'max_price' => $this->normalizeCurrency($request->input('max_price')),
        ]);

        return $request->validate([
            'q' => 'nullable|string|max:150', 'brand' => 'nullable|string|max:100',
            'source' => 'nullable|string|max:50', 'payment_method' => ['nullable', Rule::in(array_keys(Purchase::PAYMENT_METHODS))],
            'branch_id' => 'nullable|integer|exists:branches,id', 'min_price' => 'nullable|numeric|min:0',
            'max_price' => 'nullable|numeric|min:0', 'city' => 'nullable|string|max:100',
            'sort' => ['nullable', Rule::in(['newest', 'oldest', 'name_asc', 'name_desc'])],
        ]);
    }

    private function customerQuery(Request $request, array $filters)
    {
        $user = $request->user();
        $query = Customer::query()
            ->when(! $user->isCeo() && ! $user->hasRole(UserRole::Crm), fn ($q) => $q->where('branch_id', $user->branch_id))
            ->when($filters['q'] ?? null, function ($q, $term) {
                $like = '%'.$term.'%';
                $q->where(fn ($search) => $search->where('name', 'like', $like)->orWhereHas('contacts', fn ($c) => $c->where('value', 'like', $like)));
            })
            ->when($filters['source'] ?? null, fn ($q, $v) => $q->where('source', $v))
            ->when($filters['city'] ?? null, fn ($q, $v) => $q->where(fn ($area) => $area->where('city', 'like', '%'.$v.'%')->orWhere('address', 'like', '%'.$v.'%')))
            ->when(($filters['branch_id'] ?? null) && ($user->isCeo() || $user->hasRole(UserRole::Crm)), fn ($q) => $q->where('branch_id', $filters['branch_id']));

        if ($user->canManageSales()) {
            $query->when($filters['payment_method'] ?? null, fn ($q, $v) => $q->whereHas('purchases', fn ($p) => $p->where('payment_method', $v)));
            if (($filters['brand'] ?? null) || isset($filters['min_price']) || isset($filters['max_price'])) {
                $query->whereHas('purchases.items', function ($items) use ($filters) {
                    $items->when($filters['brand'] ?? null, fn ($q, $v) => $q->where('brand', $v))
                        ->when(isset($filters['min_price']), fn ($q) => $q->where('unit_price', '>=', $filters['min_price']))
                        ->when(isset($filters['max_price']), fn ($q) => $q->where('unit_price', '<=', $filters['max_price']));
                });
            }
        }

        return match ($filters['sort'] ?? 'newest') {
            'oldest' => $query->oldest(),
            'name_asc' => $query->orderBy('name'),
            'name_desc' => $query->orderByDesc('name'),
            default => $query->latest(),
        };
    }

    private function createInitialPurchase(Customer $customer, array $data, Request $request): void
    {
        $name = preg_replace('/\s+/u', ' ', trim($data['purchase_product_name']));
        $price = (float) $data['purchase_unit_price'];
        $purchase = Purchase::create([
            'customer_id' => $customer->id, 'branch_id' => $customer->branch_id, 'created_by' => $request->user()->id,
            'purchased_at' => $data['purchase_date'], 'payment_method' => $data['purchase_payment_method'],
            'total_amount' => $price, 'notes' => 'Transaksi awal saat pelanggan didaftarkan.',
        ]);
        $purchase->items()->create([
            'product_name' => $name, 'product_key' => mb_strtolower($name), 'brand' => trim($data['purchase_brand']),
            'product_type' => trim($data['purchase_product_type']), 'quantity' => 1, 'unit_price' => $price, 'subtotal' => $price,
        ]);
        $date = CarbonImmutable::parse($purchase->purchased_at);
        foreach (['after_7_days' => $date->addDays(7), 'after_1_month' => $date->addMonthNoOverflow(), 'after_6_months' => $date->addMonthsNoOverflow(6), 'after_1_year' => $date->addYearNoOverflow()] as $type => $scheduled) {
            Reminder::create(['customer_id' => $customer->id, 'purchase_id' => $purchase->id, 'branch_id' => $customer->branch_id, 'type' => $type, 'scheduled_at' => $scheduled->toDateString(), 'status' => 'pending']);
        }
        $customer->histories()->create(['user_id' => $request->user()->id, 'action' => 'purchase_created', 'note' => 'Penjualan #'.$purchase->id.' dicatat saat pendaftaran pelanggan', 'created_at' => now()]);
    }

    private function normalizeCurrency(mixed $value): mixed
    {
        if (! is_string($value)) return $value;
        $value = trim(str_replace(['Rp', 'rp', ' '], '', $value));
        return $value === '' ? null : str_replace(',', '.', str_replace('.', '', $value));
    }
}
