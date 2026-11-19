<?php

declare(strict_types=1);

namespace LifeTwin\WooCommerce\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['wp_options'] = [];
        $GLOBALS['wp_transients'] = [];
        $GLOBALS['wp_post_meta'] = [];
        $GLOBALS['wp_filters'] = [];
        $GLOBALS['lifetwin_test_products'] = [];
        $GLOBALS['lifetwin_test_terms'] = [];
    }
}
