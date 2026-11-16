<?php

namespace App\Actions\Client;

use App\Enums\TenantUserRole;
use App\Models\Client;
use App\Models\TenantUser;
use Illuminate\Validation\ValidationException;

class AuthorizeClientTransferReviewAction
{
    public function __construct(private EvaluateClientTransferAction $eligibility) {}

    /**
     * Supervisors may approve routine inactive accounts. Transfers involving
     * recent activity or open commercial work require an owner/manager decision.
     * Re-evaluate at approval time; the lookup is only informational.
     */
    public function execute(Client $client, TenantUser $reviewer): array
    {
        $evaluation = $this->eligibility->execute($client);
        $manager = in_array($reviewer->role, [TenantUserRole::Owner, TenantUserRole::Manager], true);

        if (! $manager && (! $evaluation['eligible'] || $evaluation['requires_special_review'])) {
            throw ValidationException::withMessages([
                'decision' => 'Esta transferência exige aprovação do proprietário ou gerente: cliente com atividade nos últimos 90 dias ou pendências comerciais.',
            ]);
        }

        return $evaluation;
    }
}
