<?php
/**
 * Copyright © MageDevGroup. All rights reserved.
 */
declare(strict_types=1);

namespace MageDevGroup\CustomerSsoOkta\Test\Unit;

use MageDevGroup\CustomerSsoOkta\Model\OktaPreset;
use PHPUnit\Framework\TestCase;

/**
 * Asserts di.xml wires the Okta preset into customer-sso's open/closed seam so
 * the capability core indexes it under the `okta` code.
 */
class PresetRegistrationTest extends TestCase
{
    private const REGISTRY = 'MageDevGroup\\CustomerSso\\Model\\PresetRegistry';

    public function testDiXmlRegistersOktaPresetIntoPresetRegistry(): void
    {
        $diXml = dirname(__DIR__, 2) . '/etc/di.xml';
        self::assertFileExists($diXml);

        $dom = new \DOMDocument();
        self::assertTrue($dom->load($diXml));

        $item = $this->findPresetItem($dom);

        self::assertNotNull($item, 'No "okta" preset item registered on the PresetRegistry type.');
        self::assertSame('okta', $item->getAttribute('name'));
        self::assertSame(OktaPreset::class, ltrim(trim($item->textContent), '\\'));
    }

    private function findPresetItem(\DOMDocument $dom): ?\DOMElement
    {
        foreach ($dom->getElementsByTagName('type') as $type) {
            if (ltrim($type->getAttribute('name'), '\\') !== self::REGISTRY) {
                continue;
            }
            foreach ($type->getElementsByTagName('argument') as $argument) {
                if ($argument->getAttribute('name') !== 'presets') {
                    continue;
                }
                foreach ($argument->getElementsByTagName('item') as $item) {
                    if ($item->getAttribute('name') === 'okta') {
                        return $item;
                    }
                }
            }
        }

        return null;
    }
}
