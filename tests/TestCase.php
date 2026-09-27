<?php

namespace Tests;

use App\Support\Regional;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Regional defaults are memoised statically; never leak them between tests.
        Regional::flush();
    }
}
