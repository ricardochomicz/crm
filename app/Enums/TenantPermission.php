<?php

namespace App\Enums;

enum TenantPermission: string
{
    case ViewClients = 'clients.view';
    case CreateClients = 'clients.create';
    case UpdateClients = 'clients.update';
    case DeleteClients = 'clients.delete';
    case ViewCommissions = 'commissions.view';
    case ManageCommissions = 'commissions.manage';
    case ViewCommercialGoals = 'commercial-goals.view';
    case ManageCommercialGoals = 'commercial-goals.manage';
    case ViewTeamCommissions = 'team-commissions.view';
    case ManageTeamCommissions = 'team-commissions.manage';
    case ViewOperatorClassifications = 'operator-classifications.view';
    case ManageOperatorClassifications = 'operator-classifications.manage';
    case ViewProducts = 'products.view';
    case CreateProducts = 'products.create';
    case UpdateProducts = 'products.update';
    case ManagePromotions = 'products.promotions';
    case ManageProducts = 'products.manage';
    case ManageUnits = 'units.manage';
    case UpdateCompany = 'company.update';
    case ViewMembers = 'members.view';
    case CreateMembers = 'members.create';
    case ViewMemberProfiles = 'members.profile.view';
    case UpdateMemberProfiles = 'members.profile.update';
    case UpdateMemberRole = 'members.role';
    case UpdateMemberStatus = 'members.status';
    case RemoveMember = 'members.remove';
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
            ['key' => self::ViewClients->value, 'group' => 'Clientes', 'label' => 'Visualizar clientes', 'requires' => []],
            ['key' => self::CreateClients->value, 'group' => 'Clientes', 'label' => 'Cadastrar clientes', 'requires' => ['clients.view']],
            ['key' => self::UpdateClients->value, 'group' => 'Clientes', 'label' => 'Editar clientes e contatos', 'requires' => ['clients.view']],
            ['key' => self::DeleteClients->value, 'group' => 'Clientes', 'label' => 'Excluir clientes (lixeira)', 'requires' => ['clients.view']],
            ['key' => self::ViewCommissions->value, 'group' => 'Comissões', 'label' => 'Visualizar tabelas de comissões', 'requires' => []],
            ['key' => self::ManageCommissions->value, 'group' => 'Comissões', 'label' => 'Gerenciar tabelas de comissões', 'requires' => ['commissions.view']],
            ['key' => self::ViewCommercialGoals->value, 'group' => 'Metas comerciais', 'label' => 'Visualizar metas comerciais', 'requires' => []],
            ['key' => self::ManageCommercialGoals->value, 'group' => 'Metas comerciais', 'label' => 'Gerenciar metas comerciais', 'requires' => ['commercial-goals.view']],
            ['key' => self::ViewTeamCommissions->value, 'group' => 'Comissões de equipe', 'label' => 'Visualizar comissões de equipe', 'requires' => []],
            ['key' => self::ManageTeamCommissions->value, 'group' => 'Comissões de equipe', 'label' => 'Gerenciar comissões de equipe', 'requires' => ['team-commissions.view']],
            ['key' => self::ViewOperatorClassifications->value, 'group' => 'Classificações de operadoras', 'label' => 'Visualizar classificações de operadoras', 'requires' => []],
            ['key' => self::ManageOperatorClassifications->value, 'group' => 'Classificações de operadoras', 'label' => 'Gerenciar classificações de operadoras', 'requires' => ['operator-classifications.view']],
            ['key' => self::ViewProducts->value, 'group' => 'Produtos', 'label' => 'Visualizar produtos e promoções', 'requires' => []],
            ['key' => self::CreateProducts->value, 'group' => 'Produtos', 'label' => 'Cadastrar produtos', 'requires' => ['products.view']],
            ['key' => self::UpdateProducts->value, 'group' => 'Produtos', 'label' => 'Editar produtos', 'requires' => ['products.view']],
            ['key' => self::ManagePromotions->value, 'group' => 'Produtos', 'label' => 'Gerenciar promoções', 'requires' => ['products.view']],
            ['key' => self::ManageUnits->value, 'group' => 'Empresa', 'label' => 'Gerenciar matriz, filiais e vínculos nas unidades', 'requires' => []],
            ['key' => self::UpdateCompany->value, 'group' => 'Empresa', 'label' => 'Editar dados da empresa', 'requires' => []],
            ['key' => self::ViewMembers->value, 'group' => 'Membros', 'label' => 'Visualizar membros', 'requires' => []],
            ['key' => self::CreateMembers->value, 'group' => 'Membros', 'label' => 'Cadastrar membros diretamente', 'requires' => ['members.view']],
            ['key' => self::ViewMemberProfiles->value, 'group' => 'Membros', 'label' => 'Consultar cadastro completo de colaboradores', 'requires' => ['members.view']],
            ['key' => self::UpdateMemberProfiles->value, 'group' => 'Membros', 'label' => 'Editar cadastro de colaboradores', 'requires' => ['members.profile.view']],
            ['key' => self::UpdateMemberRole->value, 'group' => 'Membros', 'label' => 'Alterar cargo dos membros', 'requires' => ['members.view']],
            ['key' => self::UpdateMemberStatus->value, 'group' => 'Membros', 'label' => 'Suspender e reativar membros', 'requires' => ['members.view']],
            ['key' => self::RemoveMember->value, 'group' => 'Membros', 'label' => 'Desvincular membros da empresa', 'requires' => ['members.view']],
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
