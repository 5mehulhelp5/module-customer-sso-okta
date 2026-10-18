<?php
/**
 * Copyright © DMLab. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0). See LICENSE.
 */
declare(strict_types=1);

namespace DmLab\CustomerSsoOkta\Test\Unit;

use PHPUnit\Framework\TestCase;

class HeaderTest extends TestCase
{
    private const OSL_LINE = ' * Licensed under the Open Software License version 3.0 (OSL-3.0). See LICENSE.';

    public function testEveryPhpAndXmlFileCarriesOslNotice(): void
    {
        $files = $this->sourceFiles();
        self::assertNotEmpty($files);

        $missing = [];
        foreach ($files as $file) {
            if (!str_contains((string) file_get_contents($file), self::OSL_LINE)) {
                $missing[] = $file;
            }
        }

        self::assertSame([], $missing, 'Files missing the OSL-3.0 notice.');
    }

    /**
     * @return string[]
     */
    private function sourceFiles(): array
    {
        $root = dirname(__DIR__, 2);
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
                static fn(\SplFileInfo $file): bool => !in_array(
                    $file->getFilename(),
                    ['.git', 'vendor', '.phpunit.cache'],
                    true
                )
            )
        );

        $files = [];
        foreach ($iterator as $file) {
            if (in_array($file->getExtension(), ['php', 'xml'], true)) {
                $files[] = $file->getPathname();
            }
        }
        sort($files);

        return $files;
    }
}
