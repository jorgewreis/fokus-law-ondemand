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

`.github/workflows/law-ci.yml` valida o Composer, instala dependências sem credenciais de produção, compila assets, executa a suíte automatizada e define um smoke runtime em Compose isolado nos pull requests. O resultado de cada commit fica nos checks do PR; esse smoke não publica imagem nem acessa o KVM1. O workflow usa `GITHUB_TOKEN` somente com permissão de leitura de conteúdo.

`.github/workflows/law-deploy.yml` só dispara com `workflow_dispatch`, no environment protegido `production-law`. Não há gatilho de push. Ele exige que `candidate_sha` seja a ponta da `main` e que o check `build-and-check` esteja verde nesse SHA. No servidor faz backup do banco, publica um diretório `releases/<sha>`, roda migrations antes de trocar o symlink `current`, recarrega o PHP-FPM, confere `https://law.ios1vcrime.com.br/novologin/up` a partir do próprio host e, se o health falhar, devolve o symlink ao release anterior. O purge da Cloudflare é opcional. Este arquivo não autoriza disparar o workflow: o gate 8 continua aberto.

Segredos nomeados, sem valor neste repositório: `LAW_DEPLOY_HOST`, `LAW_DEPLOY_PORT`, `LAW_DEPLOY_USER`, `LAW_DEPLOY_SSH_KEY`, `LAW_DEPLOY_KNOWN_HOSTS`, `LAW_DEPLOY_PATH`. Opcionais para o purge: `CLOUDFLARE_API_TOKEN` e `CLOUDFLARE_ZONE_ID`. A variável `LAW_FPM_RELOAD` pode substituir o reload padrão `sudo -n systemctl reload php8.5-fpm`.

## Origem e URL pública

O site CloudPanel é `law.ios1vcrime.com.br`, usuário `law-ondemand`, pool PHP 8.5 com `pm=ondemand` e `max_children=3`. O layout é `/home/law-ondemand/htdocs/law.ios1vcrime.com.br/{releases,shared/storage,current}`. O document root do nginx é `current/public`. O banco e o usuário MySQL são `fokuslaw` em `127.0.0.1`. O certificado é Origin CA da Cloudflare, com SSL Full Strict.

`APP_URL` de produção é `https://ios1vcrime.com.br/novologin`. Links, assets e URLs assinadas usam esse host. O pedido que chega em `law.ios1vcrime.com.br` ou pelo Worker, com `X-Forwarded-Host`, `X-Forwarded-Proto` e `X-Forwarded-Prefix`, é lido como a URL pública. Um `X-Forwarded-Host` de outro domínio é descartado. Só as faixas da Cloudflare em `config/trustedproxy.php` são proxies confiáveis.

O cookie `fokus_law_session` fica com `Path=/novologin` e sem `Domain`, para não ir a outros caminhos nem a outros hosts. `SESSION_DOMAIN` permanece vazio. `PHPSESSID` não é o cookie desta aplicação.

O trecho de nginx, com o redirecionamento de `/` para `/novologin/` na origem, está em [`docs/deploy/nginx-novologin.conf`](docs/deploy/nginx-novologin.conf). O `fastcgi_pass` continua o que o CloudPanel já usa; o arquivo não traz socket nem segredo.

## Ambientes e promoção

| Ambiente | Estado | Limite |
| --- | --- | --- |
| Local Docker | Compose sintético em `127.0.0.1:8081` | Somente dados fictícios |
| CI GitHub Actions | `law-ci.yml` em pull request e push | Sem deploy |
| Origem CloudPanel | Site, pool, banco `fokuslaw` e certificado criados; ainda sem código da aplicação | Deploy só pelo workflow manual, depois do gate 8 |
| URL pública | Worker `fokus-law-novologin-proxy` criado e ainda sem rota | Não é o sistema atual da Vara |
| Dados reais / PJe | Fora de escopo | Sem autorização |

## Gates da REI-195

Não disparar `law-deploy.yml` enquanto o gate 8 e os testes de isolamento não estiverem registrados.

| Gate | Estado em 25/11/2026 | Evidência |
| --- | --- | --- |
| 1. Cópia versionada | Atendido | Repositório `jorgewreis/fokus-law-ondemand`, raiz do app, sem worktree como fonte |
| 2. Forma de hospedagem | Atendido | CloudPanel, PHP-FPM 8.5 nativo, pool `pm=ondemand`, `max_children=3`. O Docker fica só no PC e no CI |
| 3. Site, pool, banco e isolamento | Parcial | Site `law.ios1vcrime.com.br`, usuário `law-ondemand`, banco e usuário `fokuslaw` criados. Os testes negativos ainda não rodaram no VPS |
| 4. Firewall e borda | Atendido para o Law | Grupo de firewall `409339` com a porta `3306` fechada. Certificado Origin CA e SSL Full Strict. A porta `3333` continua sendo observação anterior de outro serviço, não deste app |
| 5. Capacidade | Aberto | O espaço do KVM1 compartilhado e do PC de restauração ainda não foi medido |
| 6. Backup SFTP | Aberto | Scripts em `deploy/backup-outbox.sh` e `deploy/pc/Pull-MonthlyBackup.ps1`. Jorge ainda não executou a cópia nem o teste de restauração. Procedimento em [`docs/deploy/backup.md`](docs/deploy/backup.md) |
| 7. Acesso de manutenção | Atendido | Sem WireGuard. SSH pelo terminal do VS Code do Jorge. Interface do CloudPanel no IP do VPS, porta `8443` |
| 8. Aceite para co-hospedar | Aberto | Falta o aceite explícito do desenvolvedor. O workflow de deploy não deve ser disparado antes disso |

Os testes de isolamento também continuam abertos. O runbook está em [`docs/deploy/isolamento.md`](docs/deploy/isolamento.md). O comando `php artisan law:isolation-check --require-mysql` é a prova no servidor; a suíte só cobre a decisão e o cookie.

## Rollback

Se o health `/novologin/up` não responder 200, `deploy/remote-release.sh` devolve o symlink `current` ao release anterior e recarrega o FPM. Não desfaz migrations. O backup `backups/pre-release-<sha>-<hora>.sql.gz` feito antes da migration é o ponto de recuperação do banco, e só entra em uso por procedimento autorizado. Registrar causa, SHA anterior, SHA novo e o próximo passo no Linear, sem conteúdo de `.env` nem de dump.

## Variáveis

Locais, no `docker.env` ignorado pelo Git: `APP_KEY`, `APP_ENV`, `APP_DEBUG`, `APP_URL`, `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `MYSQL_DATABASE`, `MYSQL_USER`, `MYSQL_PASSWORD`, `MYSQL_ROOT_PASSWORD`.

No servidor, o `.env` compartilhado fica fora do release, em `shared/.env`. Produção usa `APP_URL=https://ios1vcrime.com.br/novologin`, `APP_ORIGIN_HOST=law.ios1vcrime.com.br`, `APP_PATH_PREFIX=novologin`, `DB_HOST=127.0.0.1`, `DB_DATABASE=fokuslaw` e `SESSION_DOMAIN` vazio.

Não registrar aqui valores de produção.
