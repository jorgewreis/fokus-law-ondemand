# Fokus Law: base local de build e deploy

## Estado e limites

Esta base prepara execução local sintética e verificações automatizadas. Ela não configura nem autoriza deploy no KVM1, não usa dados reais ou PJe e não constitui homologação. O KVM1 é compartilhado com outros produtos; a ativação do ambiente sintético remoto permanece bloqueada pelos controles pendentes em [ADR-0001](docs/adr/0001-fokus-law-greenfield-stack-and-boundaries.md) e na REI-195. O Compose local tem um serviço `scheduler` para executar as tarefas agendadas; isso não configura o scheduler no VPS. O código-fonte canônico é a raiz deste repositório, `jorgewreis/fokus-law-ondemand`, extraído de `apps/law` no monorepo `jorgewreis/fokus`.

## Pré-requisitos locais

- Docker Desktop ou Docker Engine com Compose v2.
- Porta local 8081 livre e espaço suficiente para imagens e volume sintético.

## Primeiro início

No PowerShell, na raiz deste repositório:

```powershell
if (Test-Path docker.env) {
    throw 'docker.env already exists; preserve it and edit it in place.'
}
Copy-Item docker.env.example docker.env
docker compose build
docker compose run --rm app php artisan key:generate --show
```

Copie o valor exibido para `APP_KEY` em `docker.env` e substitua as senhas locais de exemplo. Elas servem apenas para este banco sintético local. Não coloque segredos no Git, em issues, no PRD ou em logs.

```powershell
docker compose up -d
docker compose exec app php artisan migrate --force
docker compose ps
Invoke-WebRequest http://localhost:8081/up
```

O Compose configura o app em `127.0.0.1:8081`; MySQL não publica porta no host, e a rede Docker é interna. A porta 8080 desta workstation pertence ao Seafile; o host do Law foi movido para 8081 para evitar conflito. `/up` respondeu 200 dentro do container, mas o host recusou a conexão a 8081 apesar do bind declarado; a causa está pendente. Para parar sem remover o banco: `docker compose down`. A remoção do volume é destrutiva; siga a checagem explícita do projeto e volume em [`README.md`](README.md) antes de usar `docker compose down --volumes`.

## Pipeline

`.github/workflows/law-ci.yml` valida o Composer, instala dependências sem credenciais de produção, compila assets, executa a suíte automatizada e define um smoke runtime em Compose isolado nos pull requests. O resultado de cada commit fica nos checks do PR; esse smoke não publica imagem nem acessa o KVM1. O workflow usa `GITHUB_TOKEN` somente com permissão de leitura de conteúdo. Não há credenciais de deploy configuradas.

## Ambientes e promoção

| Ambiente | Estado | Limite |
| --- | --- | --- |
| Local Docker | Arquivo Compose foi validado; MySQL, seed, scheduler e resposta HTTP não foram iniciados/verificados nesta execução | Somente dados sintéticos; runtime no host e scheduler remoto permanecem pendentes |
| CI GitHub Actions | Workflow e smoke Compose executados por pull request; consulte os checks do commit | Sem deploy/publicação |
| Hostinger KVM1 | Não configurado para o Fokus Law | Ativar somente após os gates da REI-195 e aceite do desenvolvedor |
| Produção/dados reais | Fora do escopo aprovado | Sem autorização neste ciclo |

## Implantação remota: gates antes de habilitar

Não executar deploy remoto até registrar evidência de cada item na REI-195:

1. Trabalhar a partir da cópia versionada na raiz de `jorgewreis/fokus-law-ondemand`; não tratar worktrees temporários como diretórios-fonte adicionais.
2. Confirmar se a hospedagem será CloudPanel com PHP-FPM nativo ou container atrás do proxy; este pacote só implementa Docker local.
3. Criar site/usuário e pool PHP do Law segregados, database/user exclusivos, validar InnoDB e executar testes negativos de isolamento.
4. Esclarecer a origem e exposição da porta 3333, confirmar firewall Hostinger e demonstrar restrição de origem Cloudflare sem afetar outros sites.
5. Medir capacidade e espaço considerando Law e backup; confirmar o diretório Docker e a capacidade do PC de recuperação.
6. Configurar transferência SFTP iniciada pelo PC, criptografia, retenção das três cópias mensais e testar restauração em Docker.
7. Confirmar WireGuard e acessos operacionais para manutenção; documentar nomes de variáveis/segredos sem registrar valores.
8. Obter aceite explícito do desenvolvedor para ativar a co-hospedagem sintética.

Depois dos gates: preparar release imutável ligada a commit, fazer backup pré-release, executar migrations em etapa controlada, ativar o release, verificar `/up` e o fluxo sintético e registrar resultado/data no Linear. O mecanismo remoto e as credenciais ainda precisam ser definidos; nenhum passo desse runbook foi executado no servidor.

## Rollback

Para uma falha de aplicação, voltar à imagem/release anterior e verificar `/up` e o fluxo sintético. Não reverter automaticamente migrations nem restaurar banco: avaliar compatibilidade e usar a restauração validada somente por procedimento documentado e autorizado. Registrar causa, versão anterior/nova, evidência e próxima ação na issue Linear.

## Variáveis

`APP_KEY`, `APP_ENV`, `APP_DEBUG`, `APP_URL`, `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `MYSQL_DATABASE`, `MYSQL_USER`, `MYSQL_PASSWORD`, `MYSQL_ROOT_PASSWORD`.

Não registrar aqui valores de produção. O `docker.env` local é ignorado pelo Git.
