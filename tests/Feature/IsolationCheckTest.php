<?php

namespace Tests\Feature;

use App\Security\IsolationAssessment;
use Tests\TestCase;

class IsolationCheckTest extends TestCase
{
    public function test_assessment_fails_closed_when_a_foreign_path_is_readable(): void
    {
        $this->assertSame('pass', IsolationAssessment::database(false));
        $this->assertSame('fail', IsolationAssessment::database(true));
        $this->assertSame('skipped', IsolationAssessment::database(null));
        $this->assertSame('absent', IsolationAssessment::path(false, false));
        $this->assertSame('fail', IsolationAssessment::path(true, true));
        $this->assertSame('pass', IsolationAssessment::path(true, false));
    }

    public function test_command_fails_when_a_configured_foreign_path_is_readable(): void
    {
        config(['isolation.foreign_paths' => [sys_get_temp_dir()]]);

        $this->artisan('law:isolation-check')
            ->expectsOutputToContain('legível')
            ->assertExitCode(1);
    }

    public function test_command_does_not_treat_a_missing_path_as_proof(): void
    {
        config(['isolation.foreign_paths' => ['/tmp/fokus-law-isolamento-ausente-'.bin2hex(random_bytes(4))]]);

        $this->artisan('law:isolation-check')
            ->expectsOutputToContain('ausente')
            ->assertExitCode(0);
    }

    public function test_require_mysql_refuses_the_sqlite_test_connection(): void
    {
        config(['isolation.foreign_paths' => []]);

        $this->artisan('law:isolation-check --require-mysql')->assertExitCode(2);
    }
}
