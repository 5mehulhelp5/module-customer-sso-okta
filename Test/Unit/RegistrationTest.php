<?php
/**
 * Copyright © MageDevGroup. All rights reserved.
 */
declare(strict_types=1);

namespace MageDevGroup\CustomerSsoOkta\Test\Unit;

use Magento\Framework\Component\ComponentRegistrar;
use PHPUnit\Framework\TestCase;

class RegistrationTest extends TestCase
{
    public function testModuleIsRegistered(): void
    {
        $paths = (new ComponentRegistrar())->getPaths(ComponentRegistrar::MODULE);

        self::assertArrayHasKey('MageDevGroup_CustomerSsoOkta', $paths);
    }

    public function testRegisteredPathPointsAtThisModule(): void
    {
        $paths = (new ComponentRegistrar())->getPaths(ComponentRegistrar::MODULE);
        $path = $paths['MageDevGroup_CustomerSsoOkta'] ?? null;

        self::assertNotNull($path);
        self::assertDirectoryExists($path);
        self::assertFileExists($path . '/etc/module.xml');
    }

    public function testModuleSequencesAfterCustomerSso(): void
    {
        $paths = (new ComponentRegistrar())->getPaths(ComponentRegistrar::MODULE);
        $moduleXml = ($paths['MageDevGroup_CustomerSsoOkta'] ?? '') . '/etc/module.xml';

        $dom = new \DOMDocument();
        self::assertTrue($dom->load($moduleXml));

        $sequenced = [];
        foreach ($dom->getElementsByTagName('sequence') as $sequence) {
            foreach ($sequence->getElementsByTagName('module') as $module) {
                $sequenced[] = $module->getAttribute('name');
            }
        }

        self::assertContains('MageDevGroup_CustomerSso', $sequenced);
    }
}
