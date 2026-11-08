# Tempo real — TelecomHub

## Estado
Primeira etapa: invalidação de Pedidos Comerciais em tempo real após alterações de status e reenvio para o Backoffice. A atualização no Vue refaz a consulta autorizada; o evento não transporta dados do pedido.

## Instalação local
1. Instale o servidor Reverb e atualize o lockfile no backend: `docker compose exec app composer require laravel/reverb` (ou `composer require laravel/reverb` no host).
2. Configure no `.env` do backend: `BROADCAST_CONNECTION=reverb`, `REVERB_APP_ID=telecomhub`, `REVERB_APP_KEY=telecomhub-local-key`, `REVERB_APP_SECRET=telecomhub-local-secret`.
3. Suba o servidor: `docker compose --profile realtime up -d reverb`. O WebSocket local fica em `ws://localhost:8086`.
4. No frontend, instale as dependências atualizadas com `npm install`. Configure `.env.local`: `VITE_REVERB_APP_KEY=telecomhub-local-key`, `VITE_REVERB_HOST=localhost`, `VITE_REVERB_PORT=8086`, `VITE_REVERB_SCHEME=http`.
5. Reinicie o frontend. O backend lê as variáveis no momento da inicialização: recrie o container app se necessário.

**Importante:** em `compose.yaml`, `BROADCAST_CONNECTION` é lida da variável do Compose; apenas editar `.env` requer recriar o serviço app (`docker compose up -d --force-recreate app`). Reverb não deve ser exposto diretamente na internet sem TLS e proxy reverso.

## Segurança
O canal privado `tenant.{tenantId}.orders` exige autenticação e permissão de consulta. O evento publica somente um sinal de atualização; cada usuário consulta a API, que restringe os registros ao tenant e à carteira/equipe. Em produção, utilizar TLS/WSS e segredos exclusivos por ambiente.

## Próximas etapas
Integrar outros fluxos de pedidos (ativação/conclusão), oportunidades, agenda e carteiras; adicionar testes automatizados de autorização, reconexão e broadcasts. O polling de reconciliação permanece a cada 60 segundos até a infraestrutura estar validada.
