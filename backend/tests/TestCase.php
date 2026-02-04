<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Set up the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles after migration for RefreshDatabase tests
        $this->afterApplicationCreated(function () {
            if (\Illuminate\Support\Facades\Schema::hasTable('roles')) {
                $this->seed(\Database\Seeders\RoleSeeder::class);
            }
        });
    }
}

