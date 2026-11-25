# Backup mensal e teste de restauração

O gate 6 continua aberto. Os scripts estão no repositório; a primeira cópia e o teste de restauração ainda não foram feitos. Jorge executa os dois lados. Não grave a senha do gpg, a senha do MySQL nem a chave SSH neste repositório ou no Linear.

## No servidor, uma vez

1. Crie o grupo e o usuário restrito de backup, com SFTP preso à caixa de saída e sem shell interativo.
2. Grave a senha simétrica só em `/root/fokus-law-backup.passphrase`, modo `600`, dono root.
3. Rode como root:

```bash
bash /home/law-ondemand/htdocs/law.ios1vcrime.com.br/current/deploy/backup-outbox.sh
```

A caixa `/var/backups/fokus-law/outbox` fica de root, grupo `lawbackup`, modo `2750`. Os arquivos cifrados ficam `640`. O manifesto traz nome e SHA-256, sem segredo. O script recusa qualquer banco que não seja `fokuslaw` em `127.0.0.1`.

## No PC

Com o known_hosts já fixado:

```powershell
./deploy/pc/Pull-MonthlyBackup.ps1 `
  -SftpHost 'NOME-OU-IP-DO-VPS' `
  -SftpUser 'USUARIO-DE-BACKUP' `
  -KnownHostsFile 'C:\Users\Jorge\.ssh\known_hosts' `
  -LocalRoot 'D:\backups\fokus-law'
```

O script baixa o manifesto, confere o SHA-256 e apaga o mês mais antigo quando já existem três pastas `aaaa-mm`.

## Teste de restauração no Compose local

Não aponte este procedimento para o VPS. Use só o Compose sintético deste repositório.

1. Descifre no PC, informando a senha na hora, sem salvá-la no comando:

```powershell
gpg --output database.sql.gz --decrypt .\fokuslaw-AAAAMMDD.sql.gz.gpg
gzip -d database.sql.gz
```

2. Suba apenas o banco local, na raiz do repositório, com `docker.env` já existente e ignorado pelo Git:

```powershell
docker compose -p fokus-law-synthetic up -d db
```

3. Confirme que o container é o do projeto `fokus-law-synthetic` e que a porta do MySQL não está publicada no host. O arquivo `compose.yaml` deixa o banco só na rede interna.
4. Copie o SQL para o container e importe no banco `fokus_law_synthetic` do Compose, não no nome `fokuslaw` de produção:

```powershell
docker compose -p fokus-law-synthetic cp .\database.sql db:/tmp/restore.sql
docker compose -p fokus-law-synthetic exec db sh -c 'mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE" < /tmp/restore.sql'
```

5. Rode `php artisan migrate --force` no serviço `app` se o dump for mais antigo que o código, e abra `http://127.0.0.1:8081/up`.
6. Apague o SQL decifrado do PC quando o teste terminar. O arquivo cifrado pode ficar na pasta do mês.

Se o dump contiver a linha `CREATE DATABASE fokuslaw`, edite essa linha para o banco do Compose antes de importar, ou importe só as tabelas. Não crie no PC um banco com o nome de produção apontando para o VPS.
