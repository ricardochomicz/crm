# Manual de arquitetura e padronização — CRM

> Referência para implementar novos módulos e refatorar módulos existentes nos repositórios `crm` (Laravel) e `crm-web` (Vue 3 + TypeScript).

## 1. Princípios

1. **Responsabilidade única:** cada camada resolve um problema específico.
2. **Recursos nativos primeiro:** preferir Form Requests, Policies, Eloquent, Scopes, Resources e transações do Laravel; no frontend, Composition API, componentes e composables do Vue.
3. **Sem abstrações artificiais:** não criar `Repository` ou `Service` que apenas repassa uma chamada simples ao Eloquent.
4. **Multi-tenant por padrão:** consultas, gravações e autorizações devem respeitar a empresa ativa.
5. **Contrato de API explícito:** mudanças de paginação, filtros ou formato de resposta devem ser coordenadas entre backend e frontend.
6. **Reutilização com propósito:** extrair componentes/composables quando houver repetição ou uma responsabilidade clara.
7. **Mudanças pequenas e verificáveis:** testes automatizados, lint e build antes de considerar uma alteração concluída.

## 2. Backend — Laravel

### 2.1 Responsabilidades

| Camada | Faz | Evita |
| --- | --- | --- |
| Controller | Recebe FormRequest, chama Action/model quando simples, retorna Resource/response | `where`, `wherePivot`, regra de permissão, transação, regra de negócio |
| FormRequest | `authorize()`, `rules()`, normalização e validação complementar (`after()`) | Persistência, efeitos colaterais |
| Policy | Autorizações com usuário, tenant e recurso | Transformação de resposta |
| Action | Caso de uso: listagem complexa, criação, atualização, atribuição e operações transacionais | Conhecer detalhes HTTP ou formatar JSON |
| Service | Regra de domínio reutilizada por várias Actions, **quando necessário** | Ser mero intermediário sem lógica |
| Model | Relacionamentos, casts, scopes e invariantes simples do modelo | Conhecer request/response |
| Resource | Serialização da API e relações carregadas | Consultas inesperadas e decisões de negócio |

### 2.2 Estrutura sugerida

```text
app/
├── Actions/Client/
│   ├── FilterClientsAction.php
│   └── SaveClientAction.php
├── Http/
│   ├── Controllers/Client/ClientController.php
│   ├── Requests/Client/
│   │   ├── IndexClientRequest.php
│   │   └── SaveClientRequest.php
│   └── Resources/Client/ClientResource.php
├── Models/Client.php
├── Policies/TenantPolicy.php
└── Rules/ValidCpfCnpj.php
```

Não é obrigatório criar todos esses arquivos para uma operação CRUD simples. Criar apenas o que tiver responsabilidade real.

### 2.3 Controller enxuto

```php
public function index(
    IndexOperatorClassificationRequest $request,
    string $tenantId,
    FilterOperatorClassificationsAction $action
) {
    $tenant = $request->attributes->get('active_tenant');
    $classifications = $action->execute($tenant->id, $request->validated());

    return OperatorClassificationResource::collection($classifications)
        ->additional(['meta' => [
            'can_manage' => $request->user()
                ->can('manageOperatorClassifications', $tenant),
        ]]);
}
```

**Aceitável:** usar `Model::paginate()` diretamente no controller se a consulta for trivial, autorizada e corretamente limitada ao tenant. **Evitar:** expressões de autorização como `$tenant->users()->where(...)->wherePivot(...)->exists()` no controller.

### 2.4 FormRequest e validação

- Usar `authorize()` para a permissão da operação, preferencialmente via Policy.
- Usar `rules()` e regras customizadas (`ValidationRule`) para CPF/CNPJ, integridade e formatos.
- Usar `prepareForValidation()` para normalização (ex.: remover máscara de documento e CEP).
- Usar `after()` para validações que dependem de múltiplos campos ou vínculos.
- Usar `$request->validated()` ou `$request->safe()` ao enviar dados para a Action.
- Não depender apenas da validação do frontend.

### 2.5 Autorização e isolamento multi-tenant

- Recuperar o tenant ativo a partir do contexto autenticado, **nunca confiar em `tenant_id` enviado no corpo**.
- Escopar buscas e alterações pelo tenant, inclusive `findOrFail()` e validações de unicidade.
- Usar Policy para permissões e manter a mesma regra em FormRequests e metadados de interface.
- Diferenciar rotas globais (administrador da plataforma) das rotas de tenant.
- Não expor dados de outra empresa em relacionamentos, filtros ou Resources.

### 2.6 Actions, Eloquent e transações

- Actions representam casos de uso; nomes com verbos: `SaveClientAction`, `FilterClientsAction`.
- Scopes do Model encapsulam filtros reaproveitados; relações devem usar métodos Eloquent.
- Usar `DB::transaction()` em operações que alteram múltiplas tabelas.
- Considerar `lockForUpdate()` quando requisições concorrentes podem violar invariantes.
- Usar índices e restrições do banco quando possível para reforçar integridade.
- Evitar consultas N+1: carregar relações explicitamente com `with()` / `load()`.
- Preferir paginação do Laravel para listagens crescentes; não retornar listas arbitrariamente limitadas como substituto de paginação.

### 2.7 Resource e contrato de resposta

```php
return ClientResource::collection($clients);
```

Usar `whenLoaded()` para relacionamentos opcionais. Definir metadados (`meta`, permissões, paginação) de forma consistente, sem duplicar regras de autorização. Ao introduzir paginação, revisar os consumidores do frontend e preservar o contrato ou migrá-lo em conjunto.

### 2.8 Padrões de código

- Seguir PSR-12, nomes expressivos, tipos de parâmetros e retorno quando apropriado.
- Injeção de dependência pelo container Laravel; evitar `new Action()` dentro do controller.
- Preferir enums existentes a strings de papéis e status espalhadas pelo código.
- Evitar métodos privados genéricos no controller que concentram autorização e consultas.
- Não criar Services ou Repositories apenas para esconder uma linha de Eloquent.

## 3. Frontend — Vue 3 + TypeScript

### 3.1 Estrutura por feature

```text
src/
├── components/ui/
│   ├── ListFilters.vue
│   ├── ListTable.vue
│   ├── PagePagination.vue
│   ├── TableActionButton.vue
│   ├── SearchSelect.vue
│   └── FormError.vue
├── composables/
│   ├── useListFilters.ts
│   ├── useListPagination.ts
│   └── useFormValidation.ts
├── features/clients/
│   ├── api.ts
│   ├── components/ClientForm.vue
│   ├── composables/
│   │   ├── useClientsList.ts
│   │   ├── useClientAddress.ts
│   │   └── useClientMasks.ts
│   └── views/ClientsView.vue
├── lib/
│   └── masks.ts
└── services/
    └── viacep.ts
```

### 3.2 Responsabilidades

| Camada | Responsabilidade |
| --- | --- |
| `views/*View.vue` | Composição da página, layout e conexão entre componentes e composables |
| `features/*/components/*.vue` | Formulários, modais e blocos específicos da feature |
| `components/ui/*.vue` | Elementos reutilizáveis entre módulos, sem regra de domínio |
| `features/*/composables/use*.ts` | Estado, efeitos, filtros, paginação, submissão e lógica de interação |
| `composables/use*.ts` | Lógica genérica reutilizada entre features |
| `features/*/api.ts` | Tipos, endpoints e contrato de API |
| `services/*.ts` | Integrações externas específicas |
| `lib/*.ts` | Funções puras (máscaras, formatação, utilitários) |

### 3.3 Views enxutas

A View **não** deve conter um formulário grande inline nem acumular toda a lógica de busca, edição, validação e máscaras. Exemplo conceitual:

```vue
<script setup lang="ts">
import ClientForm from '../components/ClientForm.vue'
import { useClientsPage } from '../composables/useClientsPage'

const page = useClientsPage()
</script>

<template>
  <ListFilters @search="page.search" @clear="page.clearFilters" />
  <ListTable :rows="page.rows.value" />
  <PagePagination
    :page="page.page.value"
    :total="page.total.value"
    @change="page.changePage"
  />
  <ClientForm v-if="page.modal.value" />
</template>
```

> Exemplo ilustrativo de responsabilidades; conferir as props/eventos reais dos componentes antes de copiar.

### 3.4 Formulários

- Extrair formulários de cadastro/edição para `FeatureForm.vue` (ex.: `ClientForm.vue`).
- Usar props e eventos tipados; preferir `defineModel` quando houver edição bidirecional explícita.
- Exibir mensagens de validação com `FormError.vue` e lógica reutilizável com `useFormValidation.ts`.
- Separar máscaras e consultas externas em composables ou utilitários.
- Exibir estados de carregamento, erro e sucesso sem duplicar regras na View.
- Validar no frontend para UX, mas tratar erros de validação devolvidos pelo Laravel.

### 3.5 Listagens, filtros e paginação

- Usar `ListFilters.vue`, `ListTable.vue`, `PagePagination.vue` e `TableActionButton.vue` para manter aparência consistente.
- Filtros devem ter **Buscar** (aplicar e consultar dados) e **Limpar filtros** (resetar e recarregar).
- Separar valores digitados dos filtros efetivamente aplicados (`useListFilters.ts`).
- Para listagens pequenas locais, `useListPagination.ts` pode paginar arrays em memória.
- Para listas de banco que podem crescer, usar **paginação no servidor**: enviar `page`, `per_page` e filtros ao endpoint e consumir `meta`/`links`.
- Ao alterar filtros, retornar à primeira página; preservar filtros ao trocar de página.
- Não fazer busca local sobre uma página parcial como se representasse todos os registros.

### 3.6 API e TypeScript

- Centralizar requisições da feature em `api.ts`, usando os wrappers HTTP existentes.
- Definir tipos explícitos para entidades, inputs e respostas paginadas.
- Não espalhar strings de endpoints por Views e componentes.
- Manter os nomes e formatos dos campos alinhados aos Laravel Resources.
- Evitar `any` quando houver contrato conhecido.

## 4. Fluxo para criar um módulo novo

1. **Domínio e dados:** definir entidade, campos, relacionamentos, migrations, casts, índices e isolamento por tenant.
2. **Backend:** criar Model e scopes; FormRequests; Policy; Actions somente quando houver caso de uso; Controller enxuto; Resources; rotas.
3. **Contrato:** documentar endpoints, parâmetros, filtros, paginação, permissões e erros.
4. **Frontend:** criar `api.ts`, composable da página, View, formulário em componente próprio e componentes reutilizáveis.
5. **Validação:** cobrir casos de sucesso, entradas inválidas, permissão negada, acesso entre tenants e concorrência quando relevante.
6. **Verificação:** executar testes Laravel, análise estática/lint disponível, typecheck e build Vue; revisar mudanças de contrato.

## 5. Checklist de revisão (PR)

### Backend

- [ ] Controller apenas coordena a requisição e retorna Resource/response.
- [ ] Sem `wherePivot` ou regras de permissão inline no controller.
- [ ] FormRequest valida e autoriza; Policy centraliza permissão.
- [ ] Toda leitura/escrita de tenant está corretamente escopada.
- [ ] Actions e Services existem apenas quando agregam valor.
- [ ] Recursos e relacionamentos evitam N+1.
- [ ] Listagens crescentes usam paginação adequada.
- [ ] Operações compostas usam transação e proteção concorrente quando necessário.
- [ ] Testes cobrem validação, autorização e isolamento por tenant.

### Frontend

- [ ] View pequena e focada na composição.
- [ ] Formulário extraído para componente da feature.
- [ ] Lógica de estado, busca e envio em composables.
- [ ] Filtros, tabela, ações e paginação usam componentes compartilhados.
- [ ] Buscar e Limpar filtros funcionam de ponta a ponta.
- [ ] Tipos e API centralizados por feature.
- [ ] Loading, erros e validação estão tratados.
- [ ] Typecheck/build e testes disponíveis executados.

## 6. Referências no CRM

- **Backend:** `OperatorClassificationController`, `FilterOperatorClassificationsAction`, `SaveOperatorClassificationAction`, `TenantPolicy`, `IndexOperatorClassificationRequest` e `OperatorClassificationResource`.
- **Frontend:** `ClientsView.vue`, `ClientForm.vue`, `useClientsList.ts`, `ListFilters.vue`, `PagePagination.vue` e `useFormValidation.ts`.

**Importante:** exemplos e referências refletem o padrão desejado, não garantem que todos os módulos já estejam migrados. Refatorar gradualmente, preservando rotas, permissões e contratos existentes.
