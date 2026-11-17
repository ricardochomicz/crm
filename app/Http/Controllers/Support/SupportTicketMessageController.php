<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Events\SupportTicketChanged;
use App\Models\SupportTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupportTicketMessageController extends Controller
{
    private function ticket(Request $request, string $ticketId): SupportTicket
    {
        return SupportTicket::query()
            ->where('tenant_id', $request->attributes->get('active_tenant')->id)
            ->where('user_id', $request->user()->id)
            ->findOrFail($ticketId);
    }

    public function index(Request $request, string $tenantId, string $ticketId): JsonResponse
    {
        $ticket = $this->ticket($request, $ticketId);

        $messages = $ticket->messages()->with('author:id,name')->orderByDesc('created_at')->orderByDesc('id')->get();
        $ticket->forceFill(['customer_read_at' => now()])->save();

        return response()->json(['data' => $messages]);
    }

    public function store(Request $request, string $tenantId, string $ticketId): JsonResponse
    {
        $ticket = $this->ticket($request, $ticketId);
        abort_if($ticket->status === 'closed', 422, 'Este chamado está encerrado.');
        $validated = $request->validate(['body' => ['required', 'string', 'min:2', 'max:10000']]);
        $message = DB::transaction(function () use ($ticket, $request, $validated) {
            $message = $ticket->messages()->create(['user_id' => $request->user()->id, 'body' => $validated['body']]);
            $ticket->update(['status' => 'open']);

            return $message;
        });

        SupportTicketChanged::dispatch($ticket->id, $ticket->tenant_id, (int) $ticket->user_id);

        return response()->json(['data' => $message->load('author:id,name')], 201);
    }
}
