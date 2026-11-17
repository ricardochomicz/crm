<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
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
            ->latest()
            ->limit(50)
            ->get(['id', 'category', 'subject', 'description', 'status', 'created_at']);

        return response()->json(['data' => $tickets]);
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

        return response()->json(['data' => $ticket->only(['id', 'category', 'subject', 'description', 'status', 'created_at'])], 201);
    }
}
