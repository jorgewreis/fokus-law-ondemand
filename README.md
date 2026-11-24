# Fokus Law — ambiente sintético local

Aplicação Greenfield do Fokus Law on demand. O MVP em construção cobre Pessoas e Processos. Este ambiente usa somente fixtures fictícias; não é homologação e não autoriza PJe, dados reais, produção ou ativação no KVM1.

## Fontes canônicas

- [PRD do Fokus Law](https://app.notion.com/p/41b6af4c510981e0b980eff10759823a)
- [ADR-0001 — arquitetura Greenfield e fronteiras de ambiente](../../docs/adr/0001-fokus-law-greenfield-stack-and-boundaries.md)
- [Dicionário conceitual de Pessoas e Processos](../../docs/domain/dicionario-dominio.md)
- [Ciclo de vida das fixtures sintéticas](../../docs/testing/ciclo-fixtures-sinteticas.md)
- [REI-194 — percurso mínimo do MVP](https://linear.app/reis-developer/issue/REI-194)
- [REI-198 — aplicação e ambiente sintético](https://linear.app/reis-developer/issue/REI-198)
- [REI-197 — contas, MFA e recuperação](https://linear.app/reis-developer/issue/REI-197)
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

O transporte `array` do fluxo público de confirmação é somente para simulação: mensagens ficam em memória e não são entregues. Convites de ativação, redefinição de senha e recuperação de MFA exigem SMTP configurado e validado antes da ação; nenhum segredo de SMTP deve ser registrado em Git, Linear ou Notion. Links assinados não devem ser enviados pelo driver `log`.

## Contas, MFA e concessões

O bootstrap inicial do administrador é um comando de uso único. Ele só aceita `APP_ENV=local`, `APP_KEY` configurada, conexão MySQL no host Compose `db:3306`, banco `fokus_law_synthetic`, usuário `fokus_law_local` e `DB_URL` vazia. Nome e e-mail de Jorge devem estar configurados em `docker.env`, que é ignorado pelo Git. Execute interativamente no container local somente depois de conferir o alvo sintético:

    docker compose -p fokus-law-synthetic exec app php artisan fokus:bootstrap-admin

O comando cria Jorge inativo, sem concessões, e exibe uma senha aleatória apenas nessa execução; guarde-a em local seguro. O registro de bootstrap impede replay mesmo se a conta for alterada depois. No primeiro login, a conta precisa cadastrar TOTP e guardar os códigos de recuperação antes de obter uma sessão autenticada. Não execute o bootstrap em produção nem copie a senha para logs/tickets.

Se Jorge perder TOTP e todos os códigos de recuperação, o comando de contingência também exige o mesmo alvo local dedicado, SMTP configurado, senha atual da conta administrativa e confirmação explícita da verificação de identidade por contato previamente conhecido:

    docker compose -p fokus-law-synthetic exec app php artisan law:recover-admin-mfa

O comando revoga o segredo TOTP e os códigos anteriores, invalida sessões e exige novo cadastro TOTP. Ele envia notificação separada para o e-mail da conta e registra o evento sem incluir senha, código ou segredo. Se o SMTP falhar após a revogação, a conta permanece sem fator antigo e só pode recuperar acesso depois de novo TOTP; verifique a falha registrada antes de prosseguir.

A aprovação de uma solicitação cria uma conta inativa e emite convite de uso único com validade de 24 horas; não concede módulos nem ações. A redefinição de senha expira em 30 minutos e não redefine TOTP. Jorge configura cada concessão permitida no painel de administração; a aplicação nega por padrão e verifica módulo + ação no middleware `grant:<módulo>,<ação>`. A sessão exige MFA e expira para fins de autorização após 12 horas.

## Verificações

Execute os testes na imagem/app:

    docker compose -p fokus-law-synthetic exec -T app php artisan test --compact
    docker compose -p fokus-law-synthetic exec -T app php artisan test --compact tests/Feature/SyntheticAccountRequestSeederSafetyTest.php
    docker compose -p fokus-law-synthetic exec -T app php artisan test --compact tests/Feature/AccountLifecycleAccessTest.php

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
