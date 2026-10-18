<?php
/**
 * Copyright © DMLab. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0). See LICENSE.
 */
declare(strict_types=1);

namespace DmLab\CustomerSsoOkta\Test\Unit;

use DmLab\CustomerSsoOkta\Model\Config;
use DmLab\CustomerSsoOkta\Model\OktaPreset;
use DmLab\CustomerSsoOkta\Model\OrgDomain;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Asset\Repository as AssetRepository;
use PHPUnit\Framework\TestCase;

/**
 * Asserts i18n/en_US.csv covers every translatable phrase the module ships.
 */
class I18nTest extends TestCase
{
    /**
     * @return array<string, string>
     */
    private function translations(): array
    {
        $handle = fopen(dirname(__DIR__, 2) . '/i18n/en_US.csv', 'r');
        self::assertNotFalse($handle);
        $phrases = [];
        while (($row = fgetcsv($handle, null, ',', '"', '')) !== false) {
            self::assertCount(2, $row);
            self::assertArrayNotHasKey($row[0], $phrases, 'Duplicate key: ' . $row[0]);
            $phrases[$row[0]] = $row[1];
        }
        fclose($handle);

        return $phrases;
    }

    /**
     * @return string[]
     */
    private function systemXmlPhrases(): array
    {
        $dom = new \DOMDocument();
        self::assertTrue($dom->load(dirname(__DIR__, 2) . '/etc/adminhtml/system.xml'));
        $xpath = new \DOMXPath($dom);

        $phrases = [];
        foreach ($xpath->query('//*[@translate]') as $element) {
            foreach (preg_split('/\s+/', trim($element->getAttribute('translate'))) as $child) {
                foreach ($xpath->query($child, $element) as $node) {
                    $phrases[] = trim($node->textContent);
                }
            }
        }

        return $phrases;
    }

    public function testRowsAreKeyValuePairs(): void
    {
        self::assertNotEmpty($this->translations());
    }

    public function testButtonLabelIsTranslated(): void
    {
        $preset = new OktaPreset(
            new Config($this->createStub(ScopeConfigInterface::class)),
            new OrgDomain(),
            $this->createStub(AssetRepository::class)
        );

        self::assertArrayHasKey($preset->getButtonLabel(), $this->translations());
    }

    public function testEverySystemXmlLabelAndCommentIsTranslated(): void
    {
        $translations = $this->translations();
        $phrases = $this->systemXmlPhrases();

        self::assertContains('Org Domain', $phrases);
        self::assertCount(6, $phrases);
        foreach ($phrases as $phrase) {
            self::assertStringNotContainsString("\n", $phrase);
            self::assertArrayHasKey($phrase, $translations);
        }
    }

    public function testEveryPhpPhraseIsTranslated(): void
    {
        $translations = $this->translations();
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(dirname(__DIR__, 2) . '/Model'));
        $phrases = [];
        foreach ($files as $file) {
            if ($file->getExtension() === 'php') {
                $source = (string)file_get_contents($file->getPathname());
                preg_match_all('/__\\(([\'"])((?:(?!\\1)[^\\\\]|\\\\.)*)\\1/', $source, $m);
                array_push($phrases, ...$m[2]);
            }
        }

        self::assertNotEmpty($phrases);
        foreach ($phrases as $phrase) {
            self::assertArrayHasKey(stripslashes($phrase), $translations);
        }
    }
}
