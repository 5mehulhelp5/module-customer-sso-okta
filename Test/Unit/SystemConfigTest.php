<?php
/**
 * Copyright © MageDevGroup. All rights reserved.
 */
declare(strict_types=1);

namespace MageDevGroup\CustomerSsoOkta\Test\Unit;

use MageDevGroup\CustomerSsoOkta\Model\Config;
use PHPUnit\Framework\TestCase;

/**
 * Asserts adminhtml/system.xml exposes the Okta org-domain and auth-server fields
 * under the customer-sso section, at the exact paths {@see Config} reads, and that
 * the login-button logo the preset references ships in the frontend area.
 */
class SystemConfigTest extends TestCase
{
    /** @var \DOMXPath */
    private \DOMXPath $xpath;

    protected function setUp(): void
    {
        $systemXml = dirname(__DIR__, 2) . '/etc/adminhtml/system.xml';
        self::assertFileExists($systemXml);

        $dom = new \DOMDocument();
        self::assertTrue($dom->load($systemXml));
        $this->xpath = new \DOMXPath($dom);
    }

    public function testOktaGroupLivesUnderCustomerSsoSection(): void
    {
        $groups = $this->xpath->query(
            "/config/system/section[@id='magedevgroup_customer_sso']/group[@id='okta']"
        );

        self::assertNotNull($groups);
        self::assertSame(1, $groups->length);
    }

    public function testDomainFieldMapsToConfigPath(): void
    {
        $this->assertFieldMapsToPath('domain', Config::XML_PATH_DOMAIN);
    }

    public function testAuthServerFieldMapsToConfigPath(): void
    {
        $this->assertFieldMapsToPath('auth_server', Config::XML_PATH_AUTH_SERVER);
    }

    /**
     * Storefront SSO is tuned per store view, so the Okta fields must be store-scoped
     * to match the customer-sso section and the store-scoped reads in {@see Config}.
     */
    public function testOktaFieldsAreStoreScoped(): void
    {
        $fields = $this->xpath->query(
            "/config/system/section[@id='magedevgroup_customer_sso']"
            . "/group[@id='okta']/field"
        );

        self::assertSame(2, $fields->length);
        foreach ($fields as $field) {
            /** @var \DOMElement $field */
            self::assertSame('1', $field->getAttribute('showInStore'), 'Okta field is not store-scoped.');
        }
    }

    /**
     * Cross-group depends must be fully-qualified section/group/field paths;
     * Magento only wildcard-pads single-segment relative paths, so a two-segment
     * path resolves to a non-existent field and the show/hide toggle never binds.
     */
    public function testOktaGroupDependsAreFullyQualified(): void
    {
        $ids = $this->xpath->query(
            "/config/system/section[@id='magedevgroup_customer_sso']"
            . "/group[@id='okta']/depends/field/@id"
        );

        $paths = [];
        foreach ($ids as $attr) {
            $paths[] = $attr->value;
        }

        self::assertContains('magedevgroup_customer_sso/general/enabled', $paths);
        self::assertContains('magedevgroup_customer_sso/general/active_provider', $paths);
        foreach ($paths as $path) {
            self::assertSame(3, count(explode('/', $path)), "Depend '$path' is not fully qualified.");
        }
    }

    /**
     * The preset's button icon asset id is `MageDevGroup_CustomerSsoOkta::images/okta.svg`;
     * the storefront resolves it from the frontend area, so the file must ship there.
     */
    public function testButtonLogoAssetShipsInFrontendArea(): void
    {
        self::assertFileExists(
            dirname(__DIR__, 2) . '/view/frontend/web/images/okta.svg'
        );
    }

    /**
     * Assert the field exists in the okta group and its config path — derived from
     * the actual XML ancestry (section/group/field), with no <config_path>
     * override — equals the path the config reader uses.
     */
    private function assertFieldMapsToPath(string $fieldId, string $expectedPath): void
    {
        $fields = $this->xpath->query(
            "/config/system/section[@id='magedevgroup_customer_sso']"
            . "/group[@id='okta']/field[@id='" . $fieldId . "']"
        );

        self::assertNotNull($fields);
        self::assertSame(1, $fields->length, "Missing okta field '$fieldId'.");

        /** @var \DOMElement $field */
        $field = $fields->item(0);
        self::assertSame(
            0,
            $this->xpath->query('config_path', $field)->length,
            "Field '$fieldId' has a config_path override; its path is no longer id-derived."
        );

        $group = $field->parentNode;
        $section = $group->parentNode;
        $derivedPath = $section->getAttribute('id') . '/'
            . $group->getAttribute('id') . '/'
            . $field->getAttribute('id');

        self::assertSame($expectedPath, $derivedPath);
    }
}
