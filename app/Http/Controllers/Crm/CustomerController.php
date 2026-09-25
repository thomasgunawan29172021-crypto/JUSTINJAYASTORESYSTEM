<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Customer;
use App\Services\CustomerDedupService;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function __construct(private CustomerDedupService $dedup) {}

    public function index(Request $request)
    {
        $user = $request->user();

        $customers = Customer::query()
            ->with(['contacts', 'branch'])
            ->when(! $user->isCeo(), fn ($q) => $q->where('branch_id', $user->branch_id))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = $request->string('q');
                $q->where('name', 'like', "%{$term}%")
                    ->orWhereHas('contacts', fn ($c) => $c->where('value', 'like', "%{$term}%"));
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('crm.customers.index', compact('customers'));
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
        $data = $request->validate([
            'name'          => ['required', 'string', 'max:100'],
            'address'       => ['nullable', 'string', 'max:255'],
            'source'        => ['nullable', 'string', 'max:50'],
            'branch_id'     => ['required', 'exists:branches,id'],
            'notes'         => ['nullable', 'string'],
            'contact_type'  => ['required', 'array', 'min:1'],
            'contact_type.*' => ['in:phone,whatsapp,email,other'],
            'contact_value'  => ['required', 'array', 'min:1'],
            'contact_value.*' => ['required', 'string', 'max:150'],
            'contact_primary' => ['nullable', 'integer'], // index kontak yang dijadiin primary
            'confirm_create'  => ['nullable', 'boolean'],
        ]);

        if (! $request->user()->isManager()) {
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

        $customer = Customer::register(
            collect($data)->only(['name', 'address', 'source', 'branch_id', 'notes'])->all(),
            $contacts,
            $request->user(),
        );

        return redirect()->route('crm.customers.show', $customer)
            ->with('ok', "Pelanggan {$customer->name} berhasil didaftarkan.");
    }

    public function show(Customer $customer)
    {
        $customer->load([
            'contacts', 'branch', 'creator',
            // 'purchases' => fn ($q) => $q->latest('purchased_at')->with('items'),  // aktifkan setelah Step 2
            // 'reminders' => fn ($q) => $q->latest('due_date'),                     // aktifkan setelah Step 3
            'histories.user',
        ]);

        return view('crm.customers.show', compact('customer'));
    }

    public function edit(Request $request, Customer $customer)
    {
        $branches = Branch::all();
        $user = $request->user();

        return view('crm.customers.edit', compact('customer', 'branches', 'user'));
    }
    
    public function update(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'name'      => ['required', 'string', 'max:100'],
            'address'   => ['nullable', 'string', 'max:255'],
            'source'    => ['nullable', 'string', 'max:50'],
            'branch_id' => ['required', 'exists:branches,id'],
            'notes'     => ['nullable', 'string'],
        ]);

        if (! $request->user()->isManager()) {
            $data['branch_id'] = $request->user()->branch_id;
        }

        $customer->updateInfo($data, $request->user());

        return redirect()->route('crm.customers.show', $customer)->with('ok', 'Data pelanggan diperbarui.');
    }

    public function destroy(Request $request, Customer $customer)
    {
        $customer->histories()->create([
            'user_id'    => $request->user()->id,
            'action'     => 'deleted',
            'created_at' => now(),
        ]);
        $customer->delete(); // soft delete

        return redirect()->route('crm.customers.index')->with('ok', 'Pelanggan dihapus (masih bisa dipulihkan lewat trash).');
    }
}