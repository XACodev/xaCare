<?php

namespace Tests;

use App\Auth\PermissionTeamResolver;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Livewire\Livewire;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        PermissionTeamResolver::clearExplicitTeamId();

        // HTTP tests of Livewire pages do not flush compiled wire-key loop state;
        // leftover null $currentLoop then crashes the next test in the same parallel worker.
        Livewire::flushState();
    }
}
