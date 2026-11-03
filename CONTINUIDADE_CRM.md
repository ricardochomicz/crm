# Continuidade do desenvolvimento do CRM

> **Leia primeiro:** este arquivo registra o contexto para retomar o projeto em outro computador ou em uma nova conversa. É um retrato do código em 02/11/2026; confira o estado atual antes de continuar.

## Informações para retomar

- **Projeto:** CRM SaaS multiempresa, com API Laravel 13 e autenticação por sessão/Sanctum.
- **Objetivo atual:** implementar convites de membros para empresas, com reserva de vagas conforme o limite da assinatura.
- **Onde paramos:** enum, migration, modelo, factory, relacionamento, Action e Form Request de convite foram criados. A criação direta de membros e a consulta da assinatura já consideram as reservas.
- **Próxima ação imediata:** corrigir no `StoreTenantInvitationRequest` a permissão `addMembers` para `addMember`, que é o nome existente na `TenantPolicy`. Depois, planejar notificação, aceite e gestão dos convites antes de expor a rota.
- **Estado do fluxo:** incompleto. Ainda não há controller, notificação, endpoint de convite ou aceite; não há fluxo de envio funcional.
- **Preferência do desenvolvedor:** explicar as alterações passo a passo, por arquivo, antes de modificar o código; fornecer exemplos de commit em português. Não focar em testes nesta etapa.
- **Último commit observado:** `9c946d0 feat(convites): validar requisição de convite de membro`. O conteúdo pode mudar depois deste registro.

### Mensagem para iniciar uma nova conversa

> Leia `CONTINUIDADE_CRM.md` e confira os arquivos atuais antes de continuar. Estamos implementando convites de membros em um CRM Laravel multiempresa. Explique as alterações passo a passo, sem modificar a implementação automaticamente, e apresente exemplos de commits em português. Não foque em testes por enquanto.

## Observações importantes

- Pense e atue como um **desenvolvedor sênior**: analise o contexto e as regras de negócio antes de propor soluções, identifique riscos e explique os motivos das decisões técnicas.
- Priorize segurança, isolamento entre empresas, integridade dos dados e manutenção, sem adicionar complexidade desnecessária.
- Apresente melhorias de forma prática e incremental, respeitando a arquitetura e as convenções existentes do projeto.
- Explique o que será feito antes de propor alterações, mostre exemplos por arquivo e inclua sugestões de commit em português. Não altere a implementação automaticamente sem uma solicitação do desenvolvedor.
- Nesta etapa, concentre-se em funcionalidades e implementação; não priorize testes, conforme solicitado.

## Visão geral do sistema

O repositório contém principalmente a fundação de um SaaS multiempresa: cadastro e autenticação, empresas, membros, equipes, planos e assinaturas, controle de acesso e parte da auditoria. As rotas estão versionadas em `routes/api.php` sob `api/v1`.

Ainda não foram encontrados módulos comerciais de contatos, oportunidades, funis e atividades. A direção sugerida após a base de convites é entregar o fluxo **contato → oportunidade → atividade → ganho ou perda**.

Padrões existentes importantes:

- Controllers individuais, Form Requests e Actions.
- `EnsureActiveTenant` valida empresa ativa e vínculo ativo antes das rotas de empresa.
- `EnsureSubscriptionAllowsWrites` impede escritas operacionais quando a assinatura não permite.
- Policies controlam permissões; a de empresa está em `app/Policies/TenantPolicy.php`.
- O projeto usa UUID para empresas, vínculos e vários modelos de domínio; usuários usam ID numérico.
- Consultas de novos módulos devem ser limitadas à empresa autorizada e também passar por autorização da operação.

## Decisões de negócio para convites

1. Somente usuário autorizado a adicionar membros pode convidar.
2. O convite pertence a uma empresa e possui papel `MANAGER`, `CONSULTANT` ou `ASSISTANT`; não concede `OWNER`.
3. O prazo inicial é de **7 dias**.
4. Um convite `PENDING` com `expires_at` no futuro reserva uma vaga do plano.
5. Convites expirados, revogados ou aceitos não reservam vaga. Expiração é calculada pela data, sem status `EXPIRED`.
6. A contagem atual inclui **todos os vínculos**, inclusive suspensos, para preservar a regra preexistente. Alterar isso exige decisão de produto e cuidado com reativações.
7. Usuário com conta existente deve autenticar na conta correspondente ao e-mail convidado para aceitar; usuário sem conta precisará se cadastrar antes do aceite. Os detalhes ainda serão implementados.
8. O vínculo não deve ser criado ou ativado até o aceite. O aceite deverá trocar reserva por vínculo na mesma transação.
9. O token deve ser secreto: guardar no banco apenas seu hash e não enviar o token em resposta da API ou logs.
10. Convites poderão ser reenviados e revogados. No reenvio, a direção sugerida é gerar novo token e invalidar o anterior; a política de expiração e recuperação de falhas ainda deve ser definida.

### Cálculo de vagas

```text
vagas disponíveis = user_limit da assinatura
                  - todos os vínculos da empresa
                  - convites pendentes ainda válidos
```

O limite é copiado do plano para a assinatura. Uma vaga é uma regra de contagem; não existe tabela de vagas.

## Arquivos existentes e seu estado

### `app/Enums/TenantInvitationStatus.php`

Define `Pending`, `Accepted` e `Revoked`, com valores `PENDING`, `ACCEPTED` e `REVOKED`.

Exemplo de commit: `feat(convites): adicionar status de convite`.

### `database/migrations/2026_11_02_175832_create_tenant_invitations_table.php`

Cria `tenant_invitations` com UUID, empresa, autor, e-mail, papel, status, hash do token, datas e índices para contagem e busca. O arquivo existe; a execução da migration em cada ambiente **não foi verificada**.

Exemplo de commit: `feat(convites): criar tabela de convites de membros`.

### `app/Models/TenantInvitation.php`

Modelo com UUID, casts de papel/status/datas, relacionamentos `tenant()` e `invitedBy()`, hash oculto e método `isAvailable()`. O import de `Hidden` foi corrigido para o atributo Eloquent.

Exemplo de commit: `feat(convites): adicionar modelo de convite de membros`.

### `database/factories/TenantInvitationFactory.php`

Factory de exemplo. Ela cria empresa e autor separadamente e não garante que o autor seja membro da empresa; não representa o fluxo de autorização real.

Exemplo de commit: `feat(convites): adicionar factory de convites`.

### `app/Models/Tenant.php`

Possui o relacionamento `invitations(): HasMany`.

Exemplo de commit: `feat(empresas): adicionar relacionamento com convites`.

### `app/Actions/Tenant/CreateTenantInvitationAction.php`

Normaliza o e-mail; impede papel de proprietário; inicia transação; bloqueia empresa e assinatura; autoriza `addMember`; verifica assinatura, vínculo existente, convite válido duplicado e limite; gera token aleatório, salva seu hash e retorna convite e token para o chamador. O envio ainda não existe.

**Atenção:** foram observados caracteres de substituição em mensagens acentuadas no arquivo. Verificar e corrigir a codificação UTF-8 ao retomar, sem modificar mensagens de forma desnecessária.

Exemplo de commit: `feat(convites): criar ação de convite com reserva de vaga`.

### `app/Actions/Tenant/CreateTenantMemberAction.php`

A criação direta existente foi ajustada para bloquear empresa antes da assinatura e contar convites pendentes válidos no limite. Ainda cria usuário diretamente e envia link de definição de senha por outro controller.

**Pendência:** a criação direta ainda pode cadastrar o mesmo e-mail de um convite pendente, se houver outra vaga. Definir e implementar uma regra para impedir ou reconciliar essa situação. Também há caractere de substituição em uma mensagem de limite; verificar UTF-8.

Exemplo de commit: `fix(membros): considerar convites reservados no limite do plano`.

### `app/Http/Controllers/Tenant/ShowTenantSubscriptionController.php`

Exibe `usage.users`, `usage.reserved_invitations` e calcula `remaining.users` subtraindo usuários e reservas. A contagem apresentada é informativa e ocorre fora de uma transação de reserva.

Exemplo de commit: `feat(assinaturas): exibir vagas reservadas por convites`.

### `app/Http/Requests/Tenant/StoreTenantInvitationRequest.php`

Normaliza e valida e-mail e valida o papel permitido. Não exige que o e-mail seja novo na plataforma, pois a pessoa pode pertencer a outra empresa.

**Correção prioritária:** `authorize()` chama `can('addMembers', $tenant)`, mas `TenantPolicy` define `addMember` no singular. Trocar para `addMember` antes de conectar o endpoint.

Exemplo de commit: `fix(convites): corrigir autorização de criação de convite`.

## Concorrência e integridade

`lockForUpdate()` foi explicado como forma de coordenar operações concorrentes dentro de uma transação. A criação de convite e a criação direta seguem a ordem **tenant → assinatura**. Toda operação futura que possa consumir, liberar ou transformar reservas deve usar um protocolo consistente e ordem de bloqueio compatível.

- A Action verifica convites duplicados sob o bloqueio do tenant; o índice de e-mail da migration não é uma restrição única para convites pendentes.
- Não confiar exclusivamente no middleware para garantir que a assinatura continue válida durante uma operação transacional.
- O comportamento de bloqueios varia conforme o banco; não assumir que SQLite se comporte como PostgreSQL ou MySQL/InnoDB.
- Não enviar e-mail nem fazer chamada externa enquanto a transação de criação estiver aberta.
- Garantir que uma falha no envio deixe o convite recuperável por reenvio ou outro fluxo definido.

## Pendências para concluir convites

1. Corrigir `addMembers` para `addMember` no Form Request e verificar as mensagens com problemas de codificação.
2. Definir comportamento do cadastro direto quando há convite válido para o mesmo e-mail e aplicá-lo usando a mesma ordem de bloqueios.
3. Definir normalização e comparação de e-mail de forma consistente em cadastro, login, convites e aceite, levando em conta dados existentes e comportamento do banco.
4. Definir a interface de aceite e como o link chega ao cliente, incluindo domínio confiável, autenticação e experiência para usuário novo ou existente.
5. Criar notificação e fluxo de envio após o commit, sem expor token em logs ou respostas. Considerar falhas e reenvio.
6. Criar controller e rota de criação sob o tenant ativo, protegidos por autenticação e assinatura gravável, somente quando o envio estiver conectado.
7. Criar aceite transacional: localizar e validar token, prazo, status, titular do e-mail e empresa; verificar assinatura e vínculo existente; aplicar regras de vagas; criar vínculo e marcar convite aceito atomicamente. Evitar reutilização e corridas.
8. Implementar listagem, reenvio e revogação autorizados; invalidar token anterior ao reenviar e definir a validade renovada.
9. Revisar o limite quando houver mudança de plano, expiração durante processamento ou assinatura bloqueada, e decidir o comportamento desses cenários.
10. Avaliar se o fluxo antigo de criação direta permanecerá ou será substituído depois que convites estiverem completos.

Os itens acima são planejamentos, não funcionalidades concluídas. A ordem pode ser refinada conforme a interface de autenticação e envio for definida.

## Como mudar de computador

1. Revise `git status` e registre os arquivos desejados em commits. Este documento só estará no outro computador depois de ser incluído no repositório e enviado.
2. Envie os commits para o repositório remoto e obtenha-os no outro computador.
3. Configure o ambiente local e as credenciais de forma segura. Não envie `.env`, senhas, tokens ou dados reais ao repositório.
4. Confira dependências, banco, configuração de e-mail e o estado das migrations no novo ambiente. A existência da migration no Git não significa que ela tenha sido executada nesse banco.
5. Inicie uma conversa e use a mensagem do começo deste documento. O histórico da conversa não é transferido automaticamente pelo Git.

## Roadmap após convites

1. Definir matriz de permissões comerciais para proprietário, gerente, consultor e assistente.
2. Implementar contatos por empresa com responsável, busca e autorização.
3. Criar funil, etapas e oportunidades com isolamento por empresa e histórico de movimentação.
4. Adicionar atividades, agenda e encerramento de oportunidades como ganhas ou perdidas.
5. Evoluir assinatura paga e cobrança; atualmente existe trial e bloqueio de escrita, mas não foi identificado um ciclo completo de pagamento no repositório analisado.

## Exemplo de commit deste documento

```text
docs(projeto): registrar contexto e próximos passos do CRM
```
