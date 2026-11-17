<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlatformSupportTicketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', 'in:open,in_progress,waiting,closed'],
            'search' => ['nullable', 'string', 'max:160'],
        ]);
        $tickets = SupportTicket::query()
            ->with(['tenant:id,name', 'author:id,name,email'])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
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

        return response()->json(['data' => $message->load('author:id,name')], 201);
    }
}
