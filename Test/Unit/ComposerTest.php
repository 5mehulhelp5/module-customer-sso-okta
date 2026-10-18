<?php
/**
 * Copyright © DMLab. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0). See LICENSE.
 */
declare(strict_types=1);

namespace DmLab\CustomerSsoOkta\Test\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Asserts composer.json requires every package the module code and config reference directly.
 */
class ComposerTest extends TestCase
{
    /**
     * Namespace / config-URN prefix → package that provides it.
     */
    private const PACKAGES = [
        'DmLab\\SsoCore\\' => 'dmlab/module-sso-core',
        'DmLab\\CustomerSso\\' => 'dmlab/module-customer-sso',
        'Magento\\Framework\\' => 'magento/framework',
        'urn:magento:framework:' => 'magento/framework',
        'Magento\\Store\\' => 'magento/module-store',
        'urn:magento:module:Magento_Config:' => 'magento/module-config',
    ];

    /** @var array<string, mixed> */
    private array $composer;

    /** @var string */
    private string $moduleRoot;

    protected function setUp(): void
    {
        $this->moduleRoot = dirname(__DIR__, 2);
        $composer = json_decode(
            (string) file_get_contents($this->moduleRoot . '/composer.json'),
            true,
            flags: JSON_THROW_ON_ERROR
        );
        self::assertIsArray($composer);
        $this->composer = $composer;
    }

    public function testVersionIsSemver(): void
    {
        self::assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $this->composer['version'] ?? '');
    }

    public function testRequiresDeclaredDependencies(): void
    {
        foreach ([
            'dmlab/module-sso-core',
            'dmlab/module-customer-sso',
            'magento/module-store',
            'magento/module-config',
            'magento/framework',
        ] as $package) {
            self::assertArrayHasKey($package, $this->composer['require'], $package . ' is not required');
        }
    }

    public function testEveryDirectlyUsedPackageIsRequired(): void
    {
        $used = $this->usedPackages();

        self::assertContains('dmlab/module-sso-core', $used);
        self::assertContains('magento/module-store', $used);
        self::assertContains('magento/module-config', $used);
        foreach ($used as $package) {
            self::assertArrayHasKey($package, $this->composer['require'], $package . ' is used but not required');
        }
    }

    public function testMetadataIsDeclared(): void
    {
        self::assertSame([['name' => 'DMLab', 'homepage' => 'https://dmlab.work']], $this->composer['authors']);
        self::assertSame('https://github.com/dmlab-work/module-customer-sso-okta', $this->composer['homepage']);
        self::assertSame(
            [
                'issues' => 'https://github.com/dmlab-work/module-customer-sso-okta/issues',
                'source' => 'https://github.com/dmlab-work/module-customer-sso-okta',
            ],
            $this->composer['support']
        );
    }

    /**
     * @return list<string>
     */
    private function usedPackages(): array
    {
        $used = [];
        foreach ($this->sourceFiles() as $file) {
            $content = (string) file_get_contents($file);
            foreach (self::PACKAGES as $prefix => $package) {
                if (str_contains($content, $prefix)) {
                    $used[$package] = true;
                }
            }
        }

        return array_keys($used);
    }

    /**
     * @return list<string>
     */
    private function sourceFiles(): array
    {
        $files = [$this->moduleRoot . '/registration.php'];
        foreach (['Model', 'etc'] as $dir) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->moduleRoot . '/' . $dir, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if (in_array($file->getExtension(), ['php', 'xml'], true)) {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }
}
