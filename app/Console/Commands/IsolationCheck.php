<?php

namespace App\Console\Commands;

use App\Security\IsolationAssessment;
use Illuminate\Console\Command;
use PDO;
use PDOException;
use Throwable;

class IsolationCheck extends Command
{
    protected $signature = 'law:isolation-check {--require-mysql : Falha se esta execução não estiver no MySQL do site}';

    protected $description = 'Verifica que esta conta não lê o banco fokuscloud nem homes de outros produtos';

    public function handle(): int
    {
        $failed = false;
        $database = $this->probeDatabase();

        if ($database === 'skipped' && $this->option('require-mysql')) {
            $this->error('Conexão MySQL do site não está ativa; a prova do banco foi recusada.');

            return 2;
        }

        if ($database === 'fail') {
            $this->error('A conta do Law conseguiu abrir o banco proibido. Isso quebra o isolamento.');
            $failed = true;
        } elseif ($database === 'pass') {
            $this->info('Banco proibido inacessível com a conta do Law.');
        } else {
            $this->line('Prova do banco ignorada: a conexão atual não é MySQL.');
        }

        foreach ((array) config('isolation.foreign_paths') as $path) {
            if (! is_string($path) || $path === '') {
                continue;
            }

            $result = IsolationAssessment::path(file_exists($path), is_readable($path));
            if ($result === 'fail') {
                $this->error('Caminho estrangeiro legível: '.$path);
                $failed = true;
            } elseif ($result === 'absent') {
                $this->line('Caminho estrangeiro ausente neste host: '.$path);
            } else {
                $this->info('Caminho estrangeiro ilegível: '.$path);
            }
        }

        $cookie = (string) config('session.cookie');
        $path = (string) config('session.path');
        if ($cookie === 'PHPSESSID' || $path !== '/novologin') {
            $this->error('O cookie de sessão não está isolado em fokus_law_session com Path=/novologin.');
            $failed = true;
        } else {
            $this->info('Cookie '.$cookie.' com Path='.$path.'.');
        }

        return $failed ? 1 : 0;
    }

    private function probeDatabase(): string
    {
        if (config('database.default') !== 'mysql') {
            return IsolationAssessment::database(null);
        }

        $connection = config('database.connections.mysql');
        if (! is_array($connection)) {
            return IsolationAssessment::database(null);
        }

        $forbidden = (string) config('isolation.forbidden_database');
        $host = (string) ($connection['host'] ?? '127.0.0.1');
        $port = (string) ($connection['port'] ?? '3306');
        $user = (string) ($connection['username'] ?? '');
        $password = (string) ($connection['password'] ?? '');

        $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
        if (defined('PDO::MYSQL_ATTR_CONNECT_TIMEOUT')) {
            $options[PDO::MYSQL_ATTR_CONNECT_TIMEOUT] = 2;
        }

        try {
            $pdo = new PDO(
                "mysql:host={$host};port={$port};dbname={$forbidden}",
                $user,
                $password,
                $options,
            );
            $pdo->query('SELECT 1');

            return IsolationAssessment::database(true);
        } catch (PDOException) {
            return IsolationAssessment::database(false);
        } catch (Throwable) {
            return IsolationAssessment::database(false);
        }
    }
}
