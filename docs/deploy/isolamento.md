# Isolamento negativo do Fokus Law on demand

Em 25/11 o sistema operacional, o `open_basedir` do pool e os grants do MySQL foram conferidos. Ficam para depois do primeiro deploy o comando abaixo e a checagem dos cookies. Não cole senha, dump ou listagem de arquivos.

## O que a prova precisa mostrar

- A conta MySQL `fokuslaw`, em `127.0.0.1`, não abre o banco `fokuscloud`.
- O cookie da aplicação é `fokus_law_session` com `Path=/novologin`. O navegador não o envia para outros caminhos do domínio. `PHPSESSID` não autentica esta aplicação.
- O usuário de sistema `law-ondemand` não lê homes de outros produtos. A lista
  desses caminhos fica em `LAW_ISOLATION_FOREIGN_PATHS` no `shared/.env` do
  servidor (separados por vírgula), não neste repositório. Caminhos ausentes
  não contam como prova.

## No servidor, como law-ondemand

```bash
cd /home/law-ondemand/htdocs/law.ios1vcrime.com.br/current
php artisan law:isolation-check --require-mysql
```

Saída esperada: banco proibido inacessível, caminhos estrangeiros ilegíveis, cookie com `Path=/novologin`, código de saída 0. Se algum caminho configurado em `LAW_ISOLATION_FOREIGN_PATHS` estiver ausente, isso não conta como prova; ajuste a lista no `shared/.env` e rode de novo.

## No navegador

1. Entre por `https://ios1vcrime.com.br/novologin` depois que o Worker estiver roteado.
2. No armazenamento de cookies, `fokus_law_session` deve ter caminho `/novologin` e não deve aparecer em `/` nem em outro subdomínio.
3. Um `PHPSESSID` já existente do sistema atual não muda esta sessão.

A suíte PHP cobre o contrato do cookie e a decisão do comando. Ela não substitui a execução no VPS.
