<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApplicationHealthTest extends TestCase
{
    public function test_health_endpoint_responds_without_connecting_to_a_database(): void
    {
        $this->get('/up')->assertOk();
    }
}
