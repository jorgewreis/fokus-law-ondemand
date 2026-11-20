<?php

// Run only in a newly created disposable MySQL container on a private network.
// The caller must verify container/network isolation and remove its resources.
use App\Models\User;
use App\Security\SyntheticDatabaseGuard;
use App\Services\CompetencyCatalog;
use App\Services\ProcessClassCompetencyMap;
use App\Services\ProcessModuleSettings;
use App\Services\VerifiedSgtClassCatalog;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $exception): void {
    fwrite(STDERR, 'MySQL/InnoDB probe failed: '.get_class($exception).PHP_EOL);
    exit(1);
});

function requireProbe(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

$expectedHost = getenv('REI232_DISPOSABLE_MYSQL_HOSTNAME');
requireProbe(is_string($expectedHost) && preg_match('/^rei232-[a-f0-9]{12}$/', $expectedHost) === 1, 'Missing disposable database marker');
app(SyntheticDatabaseGuard::class)->assertAllowed();
$server = DB::selectOne('SELECT @@hostname AS hostname, DATABASE() AS database_name');
requireProbe($server->hostname === $expectedHost && $server->database_name === 'fokus_law_synthetic', 'Database identity mismatch');

if (($argv[1] ?? '') === 'worker') {
    $directory = realpath($argv[2] ?? '');
    $tempRoot = realpath(sys_get_temp_dir());
    $slot = $argv[3] ?? '';
    $revision = $argv[4] ?? '';
    requireProbe($directory !== false && $tempRoot !== false && str_starts_with($directory, $tempRoot.DIRECTORY_SEPARATOR.'rei232-barrier-') && in_array($slot, ['one', 'two'], true) && ctype_digit($revision), 'Invalid worker barrier');
    $actor = User::query()->where('email', 'rei232-probe@example.test')->firstOrFail();
    requireProbe(file_put_contents($directory.DIRECTORY_SEPARATOR.$slot.'.ready', 'ready') === 5, 'Worker signal failed');
    try {
        app(ProcessModuleSettings::class)->configure($actor, 'justica_estadual', 'primeiro_grau', $slot === 'one' ? [3] : [16], (int) $revision);
        echo 'winner';
    } catch (ValidationException $exception) {
        requireProbe(array_key_exists('revision', $exception->errors()), 'Unexpected validation error');
        echo 'stale';
    }
    exit(0);
}

$tables = DB::selectOne('SELECT COUNT(*) AS total FROM information_schema.tables WHERE table_schema = DATABASE()');
requireProbe((int) $tables->total === 0, 'Probe requires a new, empty database');

Artisan::call('migrate', ['--force' => true]);
$wrongEngine = DB::selectOne("SELECT COUNT(*) AS total FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ('process_module_settings', 'process_module_competencies', 'process_module_setting_locks', 'process_class_competencies', 'process_class_competency_events') AND engine <> 'InnoDB'");
requireProbe((int) $wrongEngine->total === 0, 'Probe requires InnoDB tables');
app(CompetencyCatalog::class)->importSnapshot();
$actor = new User;
$actor->forceFill(['name' => 'Synthetic admin', 'email' => 'rei232-probe@example.test', 'password' => 'local-probe-only', 'is_active' => true, 'is_synthetic_admin' => true, 'mfa_enabled_at' => now()])->save();
// Test-only fake; this does not identify a real SGT class.
$app->instance(VerifiedSgtClassCatalog::class, new class extends VerifiedSgtClassCatalog
{
    public function isSelectable(int $code, string $justiceBranch, string $courtLevel): bool
    {
        return $code === 12345 && $justiceBranch === 'justica_estadual' && $courtLevel === 'primeiro_grau';
    }
});
$settings = app(ProcessModuleSettings::class);
$mapping = app(ProcessClassCompetencyMap::class);
$settings->configure($actor, 'justica_estadual', 'primeiro_grau', [3, 16], 0);
$mapping->set($actor, 12345, 3, true, 1);

// MySQL signal rejects the audit insert after the mapping mutation. The entire
// configuration transaction, including its revision, must roll back.
DB::unprepared("CREATE TRIGGER rei232_reject_event BEFORE INSERT ON process_class_competency_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'probe audit failure'");
$rejected = false;
try {
    $settings->configure($actor, 'justica_estadual', 'primeiro_grau', [16], 2);
} catch (QueryException) {
    $rejected = true;
}
requireProbe($rejected, 'Audit failure was not propagated');
requireProbe($settings->current()['competencies'] === [3, 16], 'Competencies not rolled back');
requireProbe($mapping->selectableCompetencies(12345) === [3], 'Mapping not rolled back');
requireProbe($settings->snapshot()['revision'] === 2, 'Revision not rolled back');
requireProbe(DB::table('process_class_competency_events')->count() === 1, 'Unexpected audit event count');
DB::unprepared('DROP TRIGGER rei232_reject_event');

$settings->configure($actor, 'justica_estadual', 'primeiro_grau', [16], 2);
requireProbe((int) DB::table('process_class_competencies')->where('class_code', 12345)->where('competency_code', 3)->value('active') === 0, 'Retirement did not deactivate mapping');
requireProbe(DB::table('process_class_competency_events')->where('event', 'deactivated')->count() === 1, 'Missing deactivation event');
$stale = false;
try {
    $settings->configure($actor, 'justica_estadual', 'primeiro_grau', [3, 16], 2);
} catch (ValidationException $exception) {
    $stale = array_key_exists('revision', $exception->errors());
}
requireProbe($stale && $settings->snapshot()['revision'] === 3, 'Stale revision was accepted');
$settings->configure($actor, 'justica_estadual', 'primeiro_grau', [3, 16], 3);
requireProbe($mapping->selectableCompetencies(12345) === [], 'Reactivation unexpectedly restored mapping');
$mapping->set($actor, 12345, 3, true, 4);
requireProbe($mapping->selectableCompetencies(12345) === [3], 'Explicit mapping reactivation failed');

// Hold the shared singleton lock while both independent PHP processes reach the
// barrier. They submit different configurations from exactly the same revision.
$revision = $settings->snapshot()['revision'];
$eventCount = DB::table('process_module_setting_events')->count();
$mappingEventCount = DB::table('process_class_competency_events')->count();
$directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'rei232-barrier-'.bin2hex(random_bytes(12));
requireProbe(mkdir($directory, 0700), 'Cannot create probe barrier');
$workers = [];
$coordinatorLocked = false;
try {
    DB::beginTransaction();
    $coordinatorLocked = true;
    DB::table('process_module_setting_locks')->where('id', 1)->lockForUpdate()->firstOrFail();
    foreach (['one', 'two'] as $slot) {
        $pipes = [];
        $process = proc_open([PHP_BINARY, __FILE__, 'worker', $directory, $slot, (string) $revision], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        requireProbe(is_resource($process), 'Cannot start concurrency worker');
        fclose($pipes[0]);
        $workers[] = ['process' => $process, 'out' => $pipes[1], 'err' => $pipes[2]];
    }
    $deadline = microtime(true) + 15;
    while ((! is_file($directory.DIRECTORY_SEPARATOR.'one.ready') || ! is_file($directory.DIRECTORY_SEPARATOR.'two.ready')) && microtime(true) < $deadline) {
        usleep(50000);
    }
    requireProbe(is_file($directory.DIRECTORY_SEPARATOR.'one.ready') && is_file($directory.DIRECTORY_SEPARATOR.'two.ready'), 'Workers did not reach the barrier');
    // Give the workers time to enter configure while the singleton remains locked.
    usleep(250000);
    foreach ($workers as $worker) {
        requireProbe(proc_get_status($worker['process'])['running'], 'Worker exited before release');
    }
    DB::commit();
    $coordinatorLocked = false;
    $outcomes = [];
    foreach ($workers as $index => $worker) {
        $output = stream_get_contents($worker['out']);
        $error = stream_get_contents($worker['err']);
        fclose($worker['out']);
        fclose($worker['err']);
        $status = proc_close($worker['process']);
        unset($workers[$index]);
        requireProbe($status === 0 && $error === '', 'Concurrency worker failed');
        $outcomes[] = $output;
    }
    sort($outcomes);
    requireProbe($outcomes === ['stale', 'winner'], 'Unexpected concurrent outcomes');
    $result = $settings->snapshot();
    requireProbe($result['revision'] === $revision + 1, 'Concurrent revision is inconsistent');
    requireProbe(in_array($result['settings']['competencies'], [[3], [16]], true), 'Concurrent final configuration is inconsistent');
    requireProbe(DB::table('process_module_setting_events')->count() === $eventCount + 1, 'Concurrent audit event count is inconsistent');
    $event = DB::table('process_module_setting_events')->orderByDesc('id')->first();
    requireProbe((int) $event->actor_user_id === (int) $actor->getKey() && json_decode($event->before_state, true)['competencies'] === [3, 16] && json_decode($event->after_state, true)['competencies'] === $result['settings']['competencies'], 'Concurrent audit state is inconsistent');
    $retired = $result['settings']['competencies'] === [16];
    $mappingActive = (int) DB::table('process_class_competencies')->where('class_code', 12345)->where('competency_code', 3)->value('active');
    requireProbe($mappingActive === ($retired ? 0 : 1), 'Concurrent mapping state is inconsistent');
    requireProbe(DB::table('process_class_competency_events')->count() === $mappingEventCount + ($retired ? 1 : 0), 'Concurrent mapping audit count is inconsistent');
} finally {
    if ($coordinatorLocked) {
        DB::rollBack();
    }
    foreach ($workers as $worker) {
        proc_terminate($worker['process']);
        fclose($worker['out']);
        fclose($worker['err']);
        proc_close($worker['process']);
    }
    foreach (['one.ready', 'two.ready'] as $filename) {
        $path = $directory.DIRECTORY_SEPARATOR.$filename;
        if (is_file($path)) {
            unlink($path);
        }
    }
    rmdir($directory);
}

echo "MySQL/InnoDB probe: audit rollback, retirement, stale revision and concurrent writes passed.\n";
