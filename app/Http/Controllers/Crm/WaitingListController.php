<?php

namespace App\Http\Controllers\Crm;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\WaitingItem;
use App\Models\WaitingOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WaitingListController extends Controller
{
    private function customers(Request $request)
    {
        return Customer::query()->when(! $request->user()->isCeo() && ! $request->user()->hasRole(UserRole::Crm), fn ($q) => $q->where('branch_id', $request->user()->branch_id));
    }

    public function index(Request $request)
    {
        $filters = $request->validate(['q' => 'nullable|string|max:150', 'product' => 'nullable|string|max:150', 'status' => ['nullable', Rule::in(['active', 'all', ...array_keys(WaitingItem::STATUSES)])]]);
        $status = $filters['status'] ?? 'active';
        $orders = WaitingOrder::visibleTo($request->user())->with(['customer.contacts', 'items', 'creator'])
            ->whereHas('items', function ($q) use ($filters, $status) {
                if ($status === 'active') {
                    $q->where('status', '!=', 'purchased');
                } elseif ($status !== 'all') {
                    $q->where('status', $status);
                }
                if (! empty($filters['product'])) {
                    $q->where('product_key', $filters['product']);
                }
            })
            ->when(! empty($filters['q']), function ($q) use ($filters) {
                $term = '%'.$filters['q'].'%';
                $q->where(fn ($q) => $q->whereHas('items', fn ($i) => $i->where('product_name', 'like', $term))
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $term)->orWhereHas('contacts', fn ($p) => $p->where('value', 'like', $term))));
            })->latest()->paginate(15)->withQueryString();
        $recap = WaitingItem::whereHas('order', fn ($q) => $q->visibleTo($request->user()))->where('status', '!=', 'purchased')
            ->selectRaw('product_key, MIN(product_name) as product_name, SUM(quantity) as units, COUNT(*) as requests, SUM(quantity * unit_price) as total')
            ->groupBy('product_key')->orderByDesc('units')->orderBy('product_key')->paginate(10, ['*'], 'recap_page')->withQueryString();

        return view('crm.waiting-list.index', compact('orders', 'recap', 'status'));
    }

    public function create(Request $request)
    {
        $customers = $this->customers($request)->with('contacts')->when($request->filled('customer_q'), function ($q) use ($request) {
            $term = '%'.$request->string('customer_q').'%';
            $q->where(fn ($q) => $q->where('name', 'like', $term)->orWhereHas('contacts', fn ($c) => $c->where('value', 'like', $term)));
        })->orderBy('name')->limit(50)->get();
        $selected = old('customer_id', $request->input('customer_id'));
        if ($selected && ! $customers->contains('id', $selected)) {
            $customer = $this->customers($request)->with('contacts')->find($selected);
            if ($customer) {
                $customers->prepend($customer);
            }
        }
        $branches = Branch::orderBy('name')->get();

        return view('crm.waiting-list.create', compact('customers', 'branches'));
    }

    public function store(Request $request)
    {
        $request->merge([
            'items' => collect($request->input('items', []))->map(function ($item) {
                if (is_array($item) && array_key_exists('unit_price', $item)) {
                    $item['unit_price'] = $this->normalizeCurrency($item['unit_price']);
                }

                return $item;
            })->all(),
        ]);

        $data = $request->validate([
            'customer_id' => 'nullable|integer|min:1',
            'name' => 'required_without:customer_id|nullable|string|max:100',
            'phone' => ['required_without:customer_id', 'nullable', 'string', 'max:30', 'regex:/^[+0-9()\s-]+$/'],
            'branch_id' => 'required_without:customer_id|nullable|exists:branches,id',
            'notes' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1|max:100',
            'items.*.product_name' => 'required|string|max:150',
            'items.*.quantity' => 'required|integer|min:1|max:9999',
            'items.*.unit_price' => 'required|numeric|min:0|max:999999999999.99|decimal:0,2',
        ]);
        DB::transaction(function () use ($request, $data) {
            if (! empty($data['customer_id'])) {
                $customer = $this->customers($request)->findOrFail($data['customer_id']);
            } else {
                $phone = Customer::normalizePhone($data['phone']);
                if (strlen($phone) < 9 || strlen($phone) > 15) {
                    throw ValidationException::withMessages(['phone' => 'Nomor telepon harus 9–15 digit.']);
                }
                $matches = Customer::withTrashed()->whereHas('contacts', fn ($q) => $q->whereIn('type', ['phone', 'whatsapp'])->where('value', $phone))->get();
                if ($matches->isNotEmpty()) {
                    // Require explicit selection: a shared number is not proof of identity.
                    throw ValidationException::withMessages(['phone' => 'Nomor sudah terdaftar. Cari dan pilih pelanggan lama; hubungi admin bila data ada di cabang lain atau sudah dihapus.']);
                }
                $branch = $request->user()->isCeo() || $request->user()->hasRole(UserRole::Crm) ? $data['branch_id'] : $request->user()->branch_id;
                if (! $branch) {
                    throw ValidationException::withMessages(['branch_id' => 'Akun belum memiliki cabang.']);
                }
                $customer = Customer::register(['name' => $data['name'], 'branch_id' => $branch, 'source' => 'waiting-list'], [['type' => 'phone', 'value' => $phone, 'is_primary' => true]], $request->user());
            }
            $order = WaitingOrder::create(['customer_id' => $customer->id, 'created_by' => $request->user()->id, 'notes' => $data['notes'] ?? null]);
            foreach ($data['items'] as $item) {
                $name = preg_replace('/\s+/u', ' ', trim($item['product_name']));
                if ($name === '') {
                    throw ValidationException::withMessages(['items' => 'Nama produk wajib diisi.']);
                }
                $order->items()->create([...$item, 'product_name' => $name, 'product_key' => mb_strtolower($name)]);
            }
            $customer->histories()->create(['user_id' => $request->user()->id, 'action' => 'waiting_created', 'note' => 'PO #'.$order->id.' dibuat', 'created_at' => now()]);
        });

        return redirect()->route('crm.waiting-list.index')->with('ok', 'Pesanan waiting list berhasil disimpan.');
    }

    private function normalizeCurrency(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $value = trim(str_replace(['Rp', 'rp', ' '], '', $value));
        if ($value === '') {
            return $value;
        }

        $value = str_replace('.', '', $value);

        return str_replace(',', '.', $value);
    }

    public function status(Request $request, WaitingOrder $order)
    {
        WaitingOrder::visibleTo($request->user())->findOrFail($order->id);
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(WaitingItem::STATUSES))], 'item_id' => 'nullable|integer|min:1']);
        DB::transaction(function () use ($order, $request, $data) {
            $items = $order->items()->when(! empty($data['item_id']), fn ($q) => $q->whereKey($data['item_id']))->lockForUpdate()->get();
            abort_if($items->isEmpty(), 404);
            foreach ($items as $item) {
                if ($item->status === $data['status']) {
                    continue;
                }
                $before = $item->status;
                $item->update(['status' => $data['status'], 'notified_at' => $data['status'] === 'waiting' ? null : ($item->notified_at ?? ($data['status'] === 'notified' ? now() : null)), 'purchased_at' => $data['status'] === 'purchased' ? now() : null]);
                $order->customer->histories()->create(['user_id' => $request->user()->id, 'action' => 'waiting_status', 'note' => 'PO #'.$order->id.' / '.$item->product_name, 'changes' => ['before' => $before, 'after' => $data['status']], 'created_at' => now()]);
            }
        });

        return back()->with('ok', 'Status pesanan diperbarui.');
    }
}
