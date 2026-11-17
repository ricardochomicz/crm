<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
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

        return response()->json(['data' => $ticket->messages()->with('author:id,name')->oldest()->get()]);
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

        return response()->json(['data' => $message->load('author:id,name')], 201);
    }
}
