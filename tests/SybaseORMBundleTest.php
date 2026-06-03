<?php

declare(strict_types=1);

namespace SybaseORM\Bundle\Tests;

use PHPUnit\Framework\TestCase;
use SybaseORM\Bundle\SybaseORMBundle;

class SybaseORMBundleTest extends TestCase
{
    public function testBundleCanBeInstantiated(): void
    {
        $bundle = new SybaseORMBundle();

        $this->assertInstanceOf(SybaseORMBundle::class, $bundle);
    }

    public function testBundleNameIsCorrect(): void
    {
        $bundle = new SybaseORMBundle();

        $this->assertSame('SybaseORMBundle', $bundle->getName());
    }
}
