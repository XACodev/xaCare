<?php

namespace Tests;

use App\Auth\PermissionTeamResolver;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        PermissionTeamResolver::clearExplicitTeamId();
    }
}
