<?php

namespace Tests\Feature;

use Tests\TestCase;

class DeployWorkflowContractTest extends TestCase
{
    public function test_deploy_workflow_is_manual_and_names_its_secrets_without_values(): void
    {
        $workflow = file_get_contents(base_path('.github/workflows/law-deploy.yml'));

        $this->assertIsString($workflow);
        $this->assertStringContainsString('workflow_dispatch:', $workflow);
        $this->assertStringContainsString('candidate_sha:', $workflow);
        $this->assertStringContainsString('environment: production-law', $workflow);
        $this->assertStringContainsString('/novologin/up', $workflow);
        $this->assertStringContainsString('secrets.LAW_DEPLOY_HOST', $workflow);
        $this->assertStringContainsString('secrets.LAW_DEPLOY_PATH', $workflow);
        $this->assertStringContainsString('secrets.LAW_DEPLOY_SSH_KEY', $workflow);
        $this->assertStringNotContainsString("\n  push:", $workflow);
        $this->assertStringNotContainsString('pull_request:', $workflow);
        $this->assertDoesNotMatchRegularExpression('/BEGIN [A-Z ]*PRIVATE KEY/', $workflow);
        $this->assertStringNotContainsString('password=', $workflow);
    }
}
