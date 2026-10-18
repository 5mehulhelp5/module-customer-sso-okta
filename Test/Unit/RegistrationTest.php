<?php
/**
 * Copyright © DMLab. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0). See LICENSE.
 */
declare(strict_types=1);

namespace DmLab\CustomerSsoOkta\Test\Unit;

use Magento\Framework\Component\ComponentRegistrar;
use PHPUnit\Framework\TestCase;

class RegistrationTest extends TestCase
{
    public function testModuleIsRegistered(): void
    {
        $paths = (new ComponentRegistrar())->getPaths(ComponentRegistrar::MODULE);

        self::assertArrayHasKey('DmLab_CustomerSsoOkta', $paths);
    }

    public function testRegisteredPathPointsAtThisModule(): void
    {
        $paths = (new ComponentRegistrar())->getPaths(ComponentRegistrar::MODULE);
        $path = $paths['DmLab_CustomerSsoOkta'] ?? null;

        self::assertNotNull($path);
        self::assertDirectoryExists($path);
        self::assertFileExists($path . '/etc/module.xml');
    }

    public function testModuleSequencesAfterItsDependencies(): void
    {
        $paths = (new ComponentRegistrar())->getPaths(ComponentRegistrar::MODULE);
        $moduleXml = ($paths['DmLab_CustomerSsoOkta'] ?? '') . '/etc/module.xml';

        $dom = new \DOMDocument();
        self::assertTrue($dom->load($moduleXml));

        $sequenced = [];
        foreach ($dom->getElementsByTagName('sequence') as $sequence) {
            foreach ($sequence->getElementsByTagName('module') as $module) {
                $sequenced[] = $module->getAttribute('name');
            }
        }

        self::assertContains('DmLab_SsoCore', $sequenced);
        self::assertContains('DmLab_CustomerSso', $sequenced);
        self::assertContains('Magento_Config', $sequenced);
    }
}
