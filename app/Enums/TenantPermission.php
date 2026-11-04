<?php

namespace App\Enums;

enum TenantPermission: string
{
    case UpdateCompany = 'company.update';
    case ViewMembers = 'members.view';
    case CreateMembers = 'members.create';
    case UpdateMemberRole = 'members.role';
    case UpdateMemberStatus = 'members.status';
    case UpdateMemberTeam = 'members.team';
    case ViewInvitations = 'invitations.view';
    case CreateInvitations = 'invitations.create';
    case ResendInvitations = 'invitations.resend';
    case RevokeInvitations = 'invitations.revoke';
    case ViewTeams = 'teams.view';
    case CreateTeams = 'teams.create';
    case UpdateTeams = 'teams.update';
    case UpdateSupervisor = 'teams.supervisor';
    case ViewSubscription = 'subscription.view';

    /** @return list<array{key: string, group: string, label: string, requires: list<string>}> */
    public static function catalog(): array
    {
        return [
            ['key' => self::UpdateCompany->value, 'group' => 'Empresa', 'label' => 'Editar dados da empresa', 'requires' => []],
            ['key' => self::ViewMembers->value, 'group' => 'Membros', 'label' => 'Visualizar membros', 'requires' => []],
            ['key' => self::CreateMembers->value, 'group' => 'Membros', 'label' => 'Cadastrar membros diretamente', 'requires' => ['members.view']],
            ['key' => self::UpdateMemberRole->value, 'group' => 'Membros', 'label' => 'Alterar cargo dos membros', 'requires' => ['members.view']],
            ['key' => self::UpdateMemberStatus->value, 'group' => 'Membros', 'label' => 'Suspender e reativar membros', 'requires' => ['members.view']],
            ['key' => self::UpdateMemberTeam->value, 'group' => 'Membros', 'label' => 'Vincular membros às equipes', 'requires' => ['members.view']],
            ['key' => self::ViewInvitations->value, 'group' => 'Convites', 'label' => 'Visualizar convites', 'requires' => []],
            ['key' => self::CreateInvitations->value, 'group' => 'Convites', 'label' => 'Convidar pessoas', 'requires' => ['invitations.view']],
            ['key' => self::ResendInvitations->value, 'group' => 'Convites', 'label' => 'Reenviar convites', 'requires' => ['invitations.view']],
            ['key' => self::RevokeInvitations->value, 'group' => 'Convites', 'label' => 'Revogar convites', 'requires' => ['invitations.view']],
            ['key' => self::ViewTeams->value, 'group' => 'Equipes', 'label' => 'Visualizar equipes e seus membros', 'requires' => []],
            ['key' => self::CreateTeams->value, 'group' => 'Equipes', 'label' => 'Criar equipes', 'requires' => ['teams.view']],
            ['key' => self::UpdateTeams->value, 'group' => 'Equipes', 'label' => 'Editar equipes', 'requires' => ['teams.view']],
            ['key' => self::UpdateSupervisor->value, 'group' => 'Equipes', 'label' => 'Alterar supervisores', 'requires' => ['teams.view']],
            ['key' => self::ViewSubscription->value, 'group' => 'Assinatura', 'label' => 'Consultar plano e valores da assinatura', 'requires' => []],
        ];
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_column(self::cases(), 'value');
    }
}
