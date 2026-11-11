<?php

namespace App\Http\Controllers\CustomerPortal;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\CommercialOrder;
use App\Models\CustomerPortalAccess;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class CustomerPortalController extends Controller
{
    public function requestCode(Request $request, string $tenantId): JsonResponse
    {
        $data = $request->validate(['document' => ['required', 'string', 'max:32']]);
        $document = preg_replace('/\D/', '', $data['document']);
        $challenge = Str::random(64);
        $generic = ['message' => 'Se houver um contato autorizado, enviaremos um código ao e-mail cadastrado.', 'challenge' => $challenge];
        if (!in_array(strlen($document), [11, 14], true) || !Tenant::query()->whereKey($tenantId)->exists()) {
            return response()->json($generic);
        }
        $client = Client::query()->where('tenant_id', $tenantId)->where('document', $document)->where('is_active', true)->first();
        if (!$client) return response()->json($generic);

        $contact = ClientContact::query()->where('tenant_id', $tenantId)->where('client_id', $client->id)
            ->where('is_active', true)->where('portal_access_enabled', true)->whereNotNull('email')
            ->orderByDesc('is_primary')->first();
        if (!$contact || !filter_var($contact->email, FILTER_VALIDATE_EMAIL)) return response()->json($generic);

        // One active challenge per contact; a new code invalidates all previous sessions.
        CustomerPortalAccess::query()->where('tenant_id', $tenantId)->where('client_contact_id', $contact->id)->delete();
        $code = (string) random_int(100000, 999999);
        CustomerPortalAccess::query()->create([
            'tenant_id' => $tenantId, 'client_id' => $client->id, 'client_contact_id' => $contact->id,
            'challenge_hash' => hash('sha256', $challenge), 'code_hash' => Hash::make($code),
            'code_expires_at' => now()->addMinutes(5),
        ]);
        try {
            Mail::raw("Seu código de acesso ao Portal do Cliente é: {$code}. Válido por 5 minutos. Se você não solicitou, ignore esta mensagem.", function ($mail) use ($contact) {
            $mail->to($contact->email)->subject('Código de acesso ao Portal do Cliente');
        });
        } catch (\Throwable $exception) {
            \Illuminate\Support\Facades\Log::warning('Falha ao enviar código do portal do cliente', ['tenant_id' => $tenantId, 'exception' => $exception::class]);
            CustomerPortalAccess::query()->where('challenge_hash', hash('sha256', $challenge))->delete();
        }
        // Do not disclose whether the document or email exists. The challenge is only useful with the email code.
        return response()->json($generic + ['challenge' => $challenge]);
    }

    public function verify(Request $request, string $tenantId): JsonResponse
    {
        $data = $request->validate([
            'challenge' => ['required', 'string', 'size:64'],
            'code' => ['required', 'digits:6'],
        ]);
        $access = CustomerPortalAccess::query()->where('tenant_id', $tenantId)
            ->where('challenge_hash', hash('sha256', $data['challenge']))->first();
        if (!$access || $access->used_at || $access->code_expires_at->isPast() || $access->attempts >= 5) {
            return response()->json(['message' => 'Código inválido ou expirado.'], 422);
        }
        $access->increment('attempts');
        if (!Hash::check($data['code'], $access->code_hash)) {
            return response()->json(['message' => 'Código inválido ou expirado.'], 422);
        }
        $session = Str::random(64);
        $access->forceFill(['used_at' => now(), 'session_hash' => hash('sha256', $session), 'session_expires_at' => now()->addMinutes(30)])->save();
        return response()->json(['data' => ['token' => $session, 'expires_at' => $access->session_expires_at]]);
    }

    private function access(Request $request, string $tenantId): CustomerPortalAccess
    {
        $token = $request->bearerToken();
        abort_unless($token && strlen($token) === 64, 401);
        $access = CustomerPortalAccess::query()->where('tenant_id', $tenantId)
            ->where('session_hash', hash('sha256', $token))->first();
        abort_unless($access && $access->session_expires_at?->isFuture(), 401);
        abort_unless(ClientContact::query()->whereKey($access->client_contact_id)
            ->where('tenant_id', $tenantId)->where('client_id', $access->client_id)
            ->where('is_active', true)->where('portal_access_enabled', true)->exists(), 403);
        return $access;
    }

    public function orders(Request $request, string $tenantId): JsonResponse
    {
        $access = $this->access($request, $tenantId);
        $orders = CommercialOrder::query()->where('tenant_id', $tenantId)->where('client_id', $access->client_id)
            ->with(['operator:id,name', 'orderStatus:id,name'])
            ->orderByDesc('created_at')->paginate(15);
        return response()->json(['data' => $orders->getCollection()->map(fn ($order) => [
            'id' => $order->id, 'code' => $order->code, 'created_at' => $order->created_at,
            'operator' => $order->operator?->name, 'status' => $order->orderStatus?->name ?? $order->status,
            'order_type' => $order->order_type, 'line_quantity' => $order->line_quantity,
            'monthly_amount' => $order->monthly_amount, 'activated_at' => $order->activated_at,
        ]), 'meta' => ['current_page' => $orders->currentPage(), 'last_page' => $orders->lastPage(), 'total' => $orders->total()]]);
    }

    public function showOrder(Request $request, string $tenantId, string $orderId): JsonResponse
    {
        $access = $this->access($request, $tenantId);
        $order = CommercialOrder::query()->where('tenant_id', $tenantId)
            ->where('client_id', $access->client_id)->whereKey($orderId)
            ->with(['operator:id,name', 'orderStatus:id,name', 'items'])->firstOrFail();
        return response()->json(['data' => [
            'id' => $order->id, 'code' => $order->code, 'created_at' => $order->created_at,
            'operator' => $order->operator?->name, 'status' => $order->orderStatus?->name ?? $order->status,
            'order_type' => $order->order_type, 'line_quantity' => $order->line_quantity,
            'monthly_amount' => $order->monthly_amount, 'activated_at' => $order->activated_at,
            'items' => $order->items->map(fn ($item) => [
                'description' => $item->description, 'quantity' => $item->quantity,
                'monthly_unit_amount' => $item->monthly_unit_amount,
                'status' => $item->status, 'activated_at' => $item->activated_at,
            ]),
        ]]);
    }

    public function logout(Request $request, string $tenantId): JsonResponse
    {
        $access = $this->access($request, $tenantId);
        $access->forceFill(['session_hash' => null, 'session_expires_at' => null])->save();
        return response()->json(['message' => 'Sessão encerrada.']);
    }
}
