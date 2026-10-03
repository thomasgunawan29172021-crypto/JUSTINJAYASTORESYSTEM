<?php

namespace App\Http\Controllers\Crm;

use App\Enums\UserRole;
use App\Enums\WarrantyClaimStatus;
use App\Http\Controllers\Controller;
use App\Models\Reminder;
use App\Models\WaitingOrder;
use App\Models\WarrantyClaim;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FollowUpController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->validate(['status' => ['nullable', Rule::in(['due', 'upcoming', 'completed'])]])['status'] ?? 'due';
        $reminders = Reminder::visibleTo($request->user())->with(['customer.contacts', 'purchase.items', 'branch', 'completer'])
            ->when($status === 'due', fn ($q) => $q->where('status', 'pending')->whereDate('scheduled_at', '<=', today()))
            ->when($status === 'upcoming', fn ($q) => $q->where('status', 'pending')->whereDate('scheduled_at', '>', today()))
            ->when($status === 'completed', fn ($q) => $q->where('status', 'completed'))
            ->orderBy($status === 'completed' ? 'completed_at' : 'scheduled_at', $status === 'completed' ? 'desc' : 'asc')->paginate(20)->withQueryString();

        $waiting = WaitingOrder::visibleTo($request->user())->with(['customer.contacts', 'items' => fn ($q) => $q->where('status', '!=', 'purchased')])->whereHas('items', fn ($q) => $q->where('status', '!=', 'purchased'))->latest()->limit(20)->get();
        $phones = $reminders->getCollection()->flatMap(fn ($r) => $r->customer->contacts->pluck('value'))
            ->merge($waiting->flatMap(fn ($order) => $order->customer->contacts->pluck('value')))->unique();
        $claims = WarrantyClaim::with(['product', 'branch'])->whereIn('customer_phone', $phones)->whereNotIn('status', [WarrantyClaimStatus::Selesai->value, WarrantyClaimStatus::Batal->value])
            ->when(! $request->user()->isCeo() && ! $request->user()->hasRole(UserRole::Crm), fn ($q) => $q->where('branch_id', $request->user()->branch_id))->latest()->get();

        return view('crm.follow-ups.index', compact('reminders', 'waiting', 'claims', 'status'));
    }

    public function update(Request $request, Reminder $reminder)
    {
        Reminder::visibleTo($request->user())->findOrFail($reminder->id);
        $data = $request->validate(['status' => ['required', Rule::in(['completed', 'skipped'])], 'note' => 'nullable|string|max:1000']);
        $reminder->update([...$data, 'completed_by' => $request->user()->id, 'completed_at' => now()]);
        $reminder->customer->histories()->create(['user_id' => $request->user()->id, 'action' => 'followup_completed', 'note' => (Reminder::TYPES[$reminder->type] ?? $reminder->type).($data['note'] ?? null ? ': '.$data['note'] : ''), 'created_at' => now()]);

        return back()->with('ok', 'Follow-up ditandai selesai.');
    }
}
