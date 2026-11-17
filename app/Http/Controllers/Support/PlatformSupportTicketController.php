<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Events\SupportTicketChanged;
use App\Models\SupportTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlatformSupportTicketController extends Controller
{
    public function summary(): JsonResponse
    {
        $counts = SupportTicket::query()
            ->selectRaw("SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) AS open_count")
            ->selectRaw("SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) AS in_progress_count")
            ->selectRaw("SUM(CASE WHEN status = 'waiting' THEN 1 ELSE 0 END) AS waiting_count")
            ->selectRaw("SUM(CASE WHEN status != 'closed' AND priority = 'urgent' THEN 1 ELSE 0 END) AS urgent_count")
            ->first();

        return response()->json(['data' => [
            'open' => (int) ($counts->open_count ?? 0),
            'in_progress' => (int) ($counts->in_progress_count ?? 0),
            'waiting' => (int) ($counts->waiting_count ?? 0),
            'urgent' => (int) ($counts->urgent_count ?? 0),
        ]]);
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', 'in:open,in_progress,waiting,closed'],
            'priority' => ['nullable', 'in:low,normal,high,urgent'],
            'search' => ['nullable', 'string', 'max:160'],
        ]);
        $tickets = SupportTicket::query()
            ->with(['tenant:id,name', 'author:id,name,email'])
            ->withExists(['messages as has_unread_customer_message' => fn ($query) => $query
                ->whereColumn('support_ticket_messages.user_id', 'support_tickets.user_id')
                ->where(fn ($sub) => $sub->whereNull('support_tickets.admin_read_at')
                    ->orWhereColumn('support_ticket_messages.created_at', '>', 'support_tickets.admin_read_at'))])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['priority'] ?? null, fn ($query, $priority) => $query->where('priority', $priority))
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(fn ($sub) => $sub->where('subject', 'like', '%'.$search.'%')
                    ->orWhere('id', $search));
            })
            ->latest()
            ->paginate(20);

        return response()->json($tickets);
    }

    public function show(string $ticketId): JsonResponse
    {
        $ticket = SupportTicket::with(['tenant:id,name', 'author:id,name,email', 'messages.author:id,name'])
            ->findOrFail($ticketId);

        $ticket->forceFill(['admin_read_at' => now()])->save();

        return response()->json(['data' => $ticket]);
    }

    public function update(Request $request, string $ticketId): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', 'required', 'in:open,in_progress,waiting,closed'],
            'priority' => ['sometimes', 'required', 'in:low,normal,high,urgent'],
        ]);
        $ticket = SupportTicket::findOrFail($ticketId);
        $ticket->update($validated);
        SupportTicketChanged::dispatch($ticket->id, $ticket->tenant_id, (int) $ticket->user_id);

        return response()->json(['data' => $ticket->fresh()]);
    }

    public function reply(Request $request, string $ticketId): JsonResponse
    {
        $validated = $request->validate(['body' => ['required', 'string', 'min:2', 'max:10000']]);
        $ticket = SupportTicket::findOrFail($ticketId);
        $message = DB::transaction(function () use ($ticket, $request, $validated) {
            $message = $ticket->messages()->create([
                'user_id' => $request->user()->id,
                'body' => $validated['body'],
            ]);
            $ticket->update(['status' => 'waiting']);

            return $message;
        });

        SupportTicketChanged::dispatch($ticket->id, $ticket->tenant_id, (int) $ticket->user_id);

        return response()->json(['data' => $message->load('author:id,name')], 201);
    }
}
