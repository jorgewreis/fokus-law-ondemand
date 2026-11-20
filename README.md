# Fokus Law — ambiente sintético local

Aplicação Greenfield da 1ª Vara Crime de Ilhéus. O MVP em construção cobre Pessoas e Processos. Este ambiente usa somente fixtures fictícias; não é homologação e não autoriza PJe, dados reais, produção ou ativação no KVM1.

## Fontes canônicas

- [PRD do Fokus Law](https://app.notion.com/p/41b6af4c510981e0b980eff10759823a)
- [ADR-0001 — arquitetura Greenfield e fronteiras de ambiente](../../docs/adr/0001-fokus-law-greenfield-stack-and-boundaries.md)
- [Dicionário conceitual de Pessoas e Processos](../../docs/domain/dicionario-dominio.md)
- [Ciclo de vida das fixtures sintéticas](../../docs/testing/ciclo-fixtures-sinteticas.md)
- [REI-194 — percurso mínimo do MVP](https://linear.app/reis-developer/issue/REI-194/vara-implementar-o-percurso-minimo-do-mvp-institucional)
- [REI-198 — aplicação e ambiente sintético](https://linear.app/reis-developer/issue/REI-198/vara-inicializar-aplicacao-greenfield-e-ambiente-sintetico-isolado)
- [REI-197 — contas, MFA e recuperação](https://linear.app/reis-developer/issue/REI-197/vara-implementar-contas-individuais-mfa-e-recuperacao)
- [Guia local e gates de promoção](README_DEPLOYMENT.md)

## Pré-requisitos

- Docker Desktop ou Docker Engine com Compose v2.
- Porta local 8081 disponível e espaço para a imagem e o volume sintético.
- PowerShell ou shell compatível com os comandos abaixo.

## Iniciar o Compose sintético

Execute dentro de apps/law:

    if (Test-Path docker.env) { throw 'docker.env already exists; preserve it and edit it in place.' }
    Copy-Item docker.env.example docker.env

Edite docker.env: gere uma APP_KEY local e substitua as senhas de exemplo. Os valores são somente para o banco sintético local. docker.env é ignorado pelo Git; nunca copie seu conteúdo para issues, logs, Notion ou Linear.

    docker compose -p fokus-law-synthetic config --quiet
    docker compose -p fokus-law-synthetic build
    docker compose -p fokus-law-synthetic up -d
    docker compose -p fokus-law-synthetic exec -T app php artisan migrate --force --no-interaction
    docker compose -p fokus-law-synthetic exec -T app php artisan db:seed --no-interaction
    docker compose -p fokus-law-synthetic ps

O Compose publica apenas 127.0.0.1:8081 para o app. O banco não publica porta no host, e a rede Docker é interna. A configuração declara MySQL 8.4 e o volume foco-law-synthetic_law-synthetic-db; confirme o nome efetivo com docker compose config antes de qualquer limpeza.

O serviço `scheduler` executa `php artisan schedule:work`; o comando `account-requests:expire` está agendado de hora em hora. O processo apaga nome e e-mail de solicitações de conta pendentes há sete dias e marca o registro como expirado. Se o scheduler estiver parado, essa limpeza periódica não ocorre; uma nova solicitação também expira um pedido vencido para liberar um novo pedido independente.

Teste o host com Invoke-WebRequest http://127.0.0.1:8081/up. A porta 8080 pertence ao Seafile desta workstation; o Compose do Law usa 8081 para preservar esse serviço. O arquivo declara o bind em 8081, mas o runtime local do Compose não foi iniciado nesta validação; resposta HTTP pelo host e comportamento do container permanecem pendentes. O workflow Compose roda no CI de pull requests; consulte os checks do PR para o resultado mais recente. Nada disso comprova publicação ou isolamento no KVM1.

## Contrato das fixtures

O seeder SyntheticAccountRequestSeeder só aceita um destes destinos:

1. APP_ENV=testing, conexão SQLite, banco :memory: e URL de conexão vazia; ou
2. APP_ENV=local, conexão MySQL, host db, porta 3306, banco fokus_law_synthetic, usuário fokus_law_local e URL de conexão vazia.

Qualquer outra combinação lança LogicException antes de consultar o banco. O seeder usa firstOrCreate para manter uma única solicitação fictícia em pending_email_confirmation, com endereço .test, sem usuário, senha, sessão ou concessão. O seed não representa uma pessoa real nem dados mascarados. Não altere os guards para fazer fixtures alcançar outros bancos.

O transporte de e-mail do fluxo de confirmação aceita somente `array`, definido nos exemplos locais; a mensagem fica na memória e não é entregue nem fica disponível numa caixa postal. Drivers `log` e externos são recusados para evitar registrar links assinados. A confirmação é exercitada por testes com `Mail::fake()`; este Compose não oferece uma caixa postal para uso manual. O fluxo atual não cria usuário, autenticação, MFA, grants ou acesso aos módulos.

## Bootstrap administrativo único

O comando `fokus:bootstrap-admin` só aceita o SQLite `:memory:` de testes ou o MySQL isolado deste Compose, e recusa produção e qualquer outra configuração antes de solicitar segredos. Ele usa nome e e-mail definidos em configuração externa, pede o token em prompt oculto e compara seu SHA-256 com `FOKUS_ADMIN_BOOTSTRAP_TOKEN_SHA256`; o token em claro nunca é argumento, gravado no banco ou exibido. O primeiro password também é digitado em prompt oculto e confirmado. A criação da conta marcada como administradora sintética e o consumo único são atômicos. Uma segunda execução é recusada antes dos prompts. O comando não cria sessão, MFA, grants, e-mail nem permissão funcional; o marcador administrativo identifica a conta e não substitui autorização por módulo/ação.

Para uso local, mantenha `FOKUS_ADMIN_BOOTSTRAP_NAME`, `FOKUS_ADMIN_BOOTSTRAP_EMAIL` e somente o digest em `docker.env` (ignorado pelo Git); não altere `docker.env.example` com valores pessoais. Gere um token aleatório fora do repositório e calcule o SHA-256 sem imprimir nenhum dos dois. Exemplo em PowerShell, mantendo valores apenas em memória:

    $bootstrapToken = [Convert]::ToBase64String([Security.Cryptography.RandomNumberGenerator]::GetBytes(32))
    $bootstrapDigest = [Convert]::ToHexString([Security.Cryptography.SHA256]::HashData([Text.Encoding]::UTF8.GetBytes($bootstrapToken))).ToLowerInvariant()

Copie apenas o digest para `docker.env`, injete o token por meio aprovado no terminal local e execute interativamente dentro de `apps/law`:

    docker compose -p fokus-law-synthetic exec app php artisan fokus:bootstrap-admin

Cole o token somente no prompt oculto; informe o mesmo password nas duas solicitações ocultas. Após confirmar a criação, remova também `FOKUS_ADMIN_BOOTSTRAP_TOKEN_SHA256` de `docker.env` para eliminar o verificador externo; apague as variáveis locais (`Remove-Variable bootstrapToken, bootstrapDigest`). Nunca salve token ou digest em issue, log, Notion ou Linear. O comando não deve ser usado fora do Compose sintético. Bootstrap ainda não completa login/MFA nem libera módulos; aprovação e concessões permanecem etapas separadas.

## Verificações

Execute os testes na imagem/app:

    docker compose -p fokus-law-synthetic exec -T app php artisan test --compact
    docker compose -p fokus-law-synthetic exec -T app php artisan test --compact tests/Feature/SyntheticAccountRequestSeederSafetyTest.php

A primeira suíte usa o ambiente testing do PHPUnit e SQLite :memory:. O teste de segurança verifica que banco remoto, APP_ENV=production e DB_URL configurada são recusados. Em 19/11/2026, esta branch passou com 13 testes e 67 assertions. O resultado não comprova MySQL/Compose runtime, entrega HTTP pelo host ou isolamento remoto.

## Parar e remover fixtures

Para parar o app e o banco mantendo o volume, dentro de apps/law:

    docker compose -p fokus-law-synthetic down

Remover o volume apaga o banco sintético. Antes de qualquer reset destrutivo, confirme projeto e volume exatos:

    docker compose -p fokus-law-synthetic ps -a
    $lawComposeConfig = docker compose -p fokus-law-synthetic config --format json | ConvertFrom-Json
    $lawComposeConfig.name
    $lawComposeConfig.volumes.'law-synthetic-db'.name
    docker volume inspect fokus-law-synthetic_law-synthetic-db --format '{{ index .Labels "com.docker.compose.project" }} {{ index .Labels "com.docker.compose.volume" }}'

Só considere remover o volume se os resultados mostrarem exatamente o projeto fokus-law-synthetic e o volume fokus-law-synthetic_law-synthetic-db. Se não corresponderem, pare e não execute limpeza. Para esse alvo verificado, o reset remove o banco:

    docker compose -p fokus-law-synthetic down --volumes

Não use migrate:fresh, docker compose down --volumes sem -p/verificação, nem limpeza Docker global para resetar fixtures.

## Registrar uma execução

Ao registrar progresso no Linear, anote data real, branch/commit, comandos e verificações executadas, estado observado do fixture e se o alvo de limpeza foi verificado. Registre falhas e limites de runtime. Não anexe docker.env, senhas, tokens, conteúdo de logs ou valores de configuração.
