<?php

namespace ps_metrics_module_v4_1_1\PrestaShop\PsAccountsInstaller\Tests;

use ps_metrics_module_v4_1_1\Faker\Generator;
class TestCase extends \ps_metrics_module_v4_1_1\PHPUnit\Framework\TestCase
{
    /**
     * @var Generator
     */
    public $faker;
    /**
     * @return void
     */
    protected function setUp()
    {
        parent::setUp();
        $this->faker = \ps_metrics_module_v4_1_1\Faker\Factory::create();
    }
}
