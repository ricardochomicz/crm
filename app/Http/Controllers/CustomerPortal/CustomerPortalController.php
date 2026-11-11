<?php

namespace App\Http\Controllers\CustomerPortal;

use App\Enums\TenantStatus;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\CommercialOrder;
use App\Models\CustomerPortalAccess;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class CustomerPortalController extends Controller
{
    public function requestCode(Request $request): JsonResponse
    {
        $data = $request->validate(['document' => ['required', 'string', 'max:32']]);
        $document = preg_replace('/\\D/', '', $data['document']);
        $challenge = Str::random(64);
        $generic = [
            'message' => 'Se houver um contato autorizado, enviaremos um código ao e-mail cadastrado.',
            'challenge' => $challenge,
        ];
        if (! in_array(strlen($document), [11, 14], true)) {
            return response()->json($generic);
        }

        // Um documento pode existir em mais de um tenant. Cada contato autorizado
        // recebe seu próprio código, associado ao tenant correto.
        $clients = Client::query()->where('document', $document)->where('is_active', true)
            ->whereHas('contacts', fn ($query) => $query->where('is_active', true)
                ->where('portal_access_enabled', true)->whereNotNull('email'))
            ->limit(5)->get();

        foreach ($clients as $client) {
            if (! Tenant::query()->whereKey($client->tenant_id)->where('status', TenantStatus::Active)->exists()) {
                continue;
            }
            $code = (string) random_int(100000, 999999);
            $issued = DB::transaction(function () use ($client, $challenge, $code): ?array {
                $contact = ClientContact::query()->where('tenant_id', $client->tenant_id)
                    ->where('client_id', $client->id)->where('is_active', true)
                    ->where('portal_access_enabled', true)->whereNotNull('email')
                    ->orderByDesc('is_primary')->orderBy('id')->lockForUpdate()->first();
                if (! $contact || ! filter_var($contact->email, FILTER_VALIDATE_EMAIL)) {
                    return null;
                }
                $access = CustomerPortalAccess::query()->create([
                    'tenant_id' => $client->tenant_id, 'client_id' => $client->id,
                    'client_contact_id' => $contact->id,
                    'challenge_hash' => hash('sha256', $challenge.':'.$client->id),
                    'request_hash' => hash('sha256', $challenge),
                    'code_hash' => Hash::make($code),
                    'code_expires_at' => now()->addMinutes(5),
                ]);

                return ['contact' => $contact, 'access' => $access];
            }, attempts: 3);
            if ($issued === null) {
                continue;
            }
            ['contact' => $contact, 'access' => $access] = $issued;
            try {
                Mail::raw("Seu código de acesso ao Portal do Cliente é: {$code}. Válido por 5 minutos. Se você não solicitou, ignore esta mensagem.", function ($mail) use ($contact) {
                    $mail->to($contact->email)->subject('Código de acesso ao Portal do Cliente');
                });
            } catch (\Throwable $exception) {
                Log::warning('Falha no envio do código do portal', [
                    'tenant_id' => $client->tenant_id, 'exception' => $exception::class,
                ]);
                $access->delete();
            }
        }

        return response()->json($generic);
    }

    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'challenge' => ['required', 'string', 'size:64'],
            'code' => ['required', 'digits:6'],
        ]);

        return DB::transaction(function () use ($data): JsonResponse {
            $accesses = CustomerPortalAccess::query()
                ->where('request_hash', hash('sha256', $data['challenge']))
                ->whereNull('used_at')->where('code_expires_at', '>', now())
                ->where('attempts', '<', 5)->orderBy('id')->lockForUpdate()->get();
            $match = null;
            foreach ($accesses as $candidate) {
                if (! $candidate->code_expires_at?->isFuture()) {
                    continue;
                }
                if (! hash_equals($candidate->challenge_hash, hash('sha256', $data['challenge'].':'.$candidate->client_id))) {
                    continue;
                }
                $candidate->increment('attempts');
                if (Hash::check($data['code'], $candidate->code_hash) && $this->isAuthorized($candidate)) {
                    $match = $candidate;
                    break;
                }
            }
            if (! $match) {
                return response()->json(['message' => 'Código inválido ou expirado.'], 422);
            }
            $session = Str::random(64);
            $match->forceFill([
                'used_at' => now(), 'session_hash' => hash('sha256', $session),
                'session_expires_at' => now()->addMinutes(30),
            ])->save();
            CustomerPortalAccess::query()->where('client_contact_id', $match->client_contact_id)
                ->whereKeyNot($match->id)->whereNull('used_at')->delete();
            $clientName = Client::query()->where('tenant_id', $match->tenant_id)
                ->whereKey($match->client_id)->value('company_name');

            return response()->json(['data' => [
                'tenant_id' => $match->tenant_id, 'client_name' => $clientName, 'token' => $session,
                'expires_at' => $match->session_expires_at,
            ]]);
        }, attempts: 3);
    }

    private function access(Request $request, string $tenantId): CustomerPortalAccess
    {
        $token = $request->bearerToken();
        abort_unless($token && strlen($token) === 64, 401);
        $access = CustomerPortalAccess::query()->where('tenant_id', $tenantId)
            ->where('session_hash', hash('sha256', $token))->first();
        abort_unless($access && $access->session_expires_at?->isFuture(), 401);
        abort_unless($this->isAuthorized($access), 403);

        return $access;
    }

    private function isAuthorized(CustomerPortalAccess $access): bool
    {
        return Tenant::query()->whereKey($access->tenant_id)->where('status', TenantStatus::Active)->exists()
            && Client::query()->whereKey($access->client_id)->where('tenant_id', $access->tenant_id)->where('is_active', true)->exists()
            && ClientContact::query()->whereKey($access->client_contact_id)
                ->where('tenant_id', $access->tenant_id)->where('client_id', $access->client_id)
                ->where('is_active', true)->where('portal_access_enabled', true)->exists();
    }

    public function orders(Request $request, string $tenantId): JsonResponse
    {
        $access = $this->access($request, $tenantId);
        $orders = CommercialOrder::query()->where('tenant_id', $tenantId)->where('client_id', $access->client_id)
            ->with(['operator:id,name', 'orderStatus:id,name', 'consultant.user:id,name'])
            ->orderByRaw('CASE WHEN activated_at IS NULL THEN 0 ELSE 1 END ASC')
            ->orderByDesc('activated_at')->orderByDesc('created_at')->orderByDesc('id')->paginate(15);
        $clientName = Client::query()->where('tenant_id', $tenantId)
            ->whereKey($access->client_id)->value('company_name');

        return response()->json(['client_name' => $clientName, 'data' => $orders->getCollection()->map(fn ($order) => [
            'id' => $order->id, 'code' => $order->code, 'created_at' => $order->created_at,
            'operator' => $order->operator?->name, 'status' => $order->orderStatus?->name ?? $order->status,
            'order_type' => $order->order_type, 'line_quantity' => $order->line_quantity,
            'monthly_amount' => $order->monthly_amount, 'activated_at' => $order->activated_at,
            'expected_activation_at' => $order->expected_activation_at,
            'consultant_name' => $order->consultant?->user?->name,
        ]), 'meta' => ['current_page' => $orders->currentPage(), 'last_page' => $orders->lastPage(), 'total' => $orders->total()]]);
    }

    public function showOrder(Request $request, string $tenantId, string $orderId): JsonResponse
    {
        $access = $this->access($request, $tenantId);
        $order = CommercialOrder::query()->where('tenant_id', $tenantId)
            ->where('client_id', $access->client_id)->whereKey($orderId)
            ->with(['operator:id,name', 'orderStatus:id,name', 'consultant.user:id,name', 'items'])->firstOrFail();

        return response()->json(['data' => [
            'id' => $order->id, 'code' => $order->code, 'created_at' => $order->created_at,
            'operator' => $order->operator?->name, 'status' => $order->orderStatus?->name ?? $order->status,
            'order_type' => $order->order_type, 'line_quantity' => $order->line_quantity,
            'monthly_amount' => $order->monthly_amount, 'activated_at' => $order->activated_at,
            'consultant_name' => $order->consultant?->user?->name,
            'items' => $order->items->map(fn ($item) => [
                'description' => $item->description, 'quantity' => $item->quantity,
                'line_number' => $item->line_number,
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
