<?php

namespace Tests\Unit;

use App\Security\IsolationPaths;
use PHPUnit\Framework\TestCase;

class IsolationPathsTest extends TestCase
{
    public function test_parse_uses_generic_default_when_the_value_is_missing_or_blank(): void
    {
        $this->assertSame(
            ['/home/other-app', '/home/other-site'],
            IsolationPaths::parse(null),
        );
        $this->assertSame(
            ['/home/other-app', '/home/other-site'],
            IsolationPaths::parse('   '),
        );
    }

    public function test_parse_splits_a_comma_separated_list_and_ignores_empty_items(): void
    {
        $this->assertSame(
            ['/home/site-a', '/home/site-b'],
            IsolationPaths::parse(' /home/site-a , , /home/site-b '),
        );
    }
}
