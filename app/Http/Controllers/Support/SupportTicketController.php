<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Events\SupportTicketChanged;
use App\Models\SupportTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupportTicketController extends Controller
{
    public function index(Request $request, string $tenantId): JsonResponse
    {
        $tickets = SupportTicket::query()
            ->where('tenant_id', $request->attributes->get('active_tenant')->id)
            ->where('user_id', $request->user()->id)
            ->withExists(['messages as has_unread_reply' => function ($query) use ($request) {
                $query->where('user_id', '!=', $request->user()->id)
                    ->where(function ($sub) {
                        $sub->whereColumn('support_ticket_messages.created_at', '>', 'support_tickets.customer_read_at')
                            ->orWhereNull('support_tickets.customer_read_at');
                    });
            }])
            ->latest()
            ->limit(50)
            ->get(['id', 'category', 'subject', 'description', 'status', 'customer_read_at', 'created_at']);

        return response()->json(['data' => $tickets]);
    }

    public function unreadCount(Request $request, string $tenantId): JsonResponse
    {
        $count = SupportTicket::query()
            ->where('tenant_id', $request->attributes->get('active_tenant')->id)
            ->where('user_id', $request->user()->id)
            ->whereHas('messages', function ($query) use ($request) {
                $query->where('user_id', '!=', $request->user()->id)
                    ->where(function ($sub) {
                        $sub->whereColumn('support_ticket_messages.created_at', '>', 'support_tickets.customer_read_at')
                            ->orWhereNull('support_tickets.customer_read_at');
                    });
            })->count();

        return response()->json(['data' => ['count' => $count]]);
    }

    public function store(Request $request, string $tenantId): JsonResponse
    {
        $validated = $request->validate([
            'category' => ['required', 'in:technical,question,suggestion,other'],
            'subject' => ['required', 'string', 'min:5', 'max:160'],
            'description' => ['required', 'string', 'min:10', 'max:10000'],
        ]);

        $ticket = DB::transaction(fn () => SupportTicket::create([
            ...$validated,
            'tenant_id' => $request->attributes->get('active_tenant')->id,
            'user_id' => $request->user()->id,
            'status' => 'open',
        ]));

        SupportTicketChanged::dispatch($ticket->id, $ticket->tenant_id, (int) $ticket->user_id);

        return response()->json(['data' => $ticket->only(['id', 'category', 'subject', 'description', 'status', 'created_at'])], 201);
    }
}
