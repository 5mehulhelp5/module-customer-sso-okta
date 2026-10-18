<?php
/**
 * Copyright © DMLab. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0). See LICENSE.
 */
declare(strict_types=1);

namespace DmLab\CustomerSsoOkta\Test\Unit\Model;

use DmLab\CustomerSsoOkta\Model\Config;
use DmLab\CustomerSsoOkta\Model\OktaPreset;
use DmLab\CustomerSsoOkta\Model\OrgDomain;
use DmLab\SsoCore\Api\ProviderPresetInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Asset\Repository as AssetRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class OktaPresetTest extends TestCase
{
    /** @var OktaPreset preset under test, built over an empty Okta config. */
    private OktaPreset $preset;

    protected function setUp(): void
    {
        $this->preset = $this->preset();
    }

    /**
     * Build a preset over an Okta config stubbed from the given store values.
     *
     * @param array<string,mixed> $storeValues
     */
    private function preset(array $storeValues = []): OktaPreset
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')
            ->willReturnCallback(static fn (string $path) => $storeValues[$path] ?? null);

        $assetRepository = $this->createStub(AssetRepository::class);
        $assetRepository->method('getUrl')
            ->willReturnCallback(
                static fn (string $fileId) => 'https://magento.loc/static/' . $fileId
            );

        return new OktaPreset(new Config($scopeConfig), new OrgDomain(), $assetRepository);
    }

    public function testImplementsProviderPresetContract(): void
    {
        self::assertInstanceOf(ProviderPresetInterface::class, $this->preset);
    }

    public function testExposesIdentity(): void
    {
        self::assertSame('okta', $this->preset->getCode());
        self::assertSame('Okta', $this->preset->getLabel());
    }

    public function testOrgServerScopesIncludeGroups(): void
    {
        self::assertSame(
            ['openid', 'profile', 'email', 'groups'],
            $this->preset->getDefaultScopes()
        );
    }

    /**
     * Custom servers reject the `groups` scope with `invalid_scope`.
     */
    public function testCustomServerScopesOmitGroups(): void
    {
        $preset = $this->preset([Config::XML_PATH_AUTH_SERVER => 'default']);

        self::assertSame(['openid', 'profile', 'email'], $preset->getDefaultScopes());
    }

    public function testWhitespaceStoredAuthServerMeansOrgServer(): void
    {
        $preset = $this->preset([Config::XML_PATH_AUTH_SERVER => '   ']);

        self::assertContains('groups', $preset->getDefaultScopes());
    }

    public function testSlashOnlyStoredAuthServerMeansOrgServer(): void
    {
        $preset = $this->preset([Config::XML_PATH_AUTH_SERVER => '/']);

        self::assertContains('groups', $preset->getDefaultScopes());
    }

    public function testSlashWrappedStoredAuthServerIsCustomServer(): void
    {
        $preset = $this->preset([Config::XML_PATH_AUTH_SERVER => '/default/']);

        self::assertSame(['openid', 'profile', 'email'], $preset->getDefaultScopes());
    }

    /**
     * `email` carries `email_verified`, required for adopting an account by email.
     *
     * @return array<string,array{0:array<string,mixed>}>
     */
    public static function serverTypeProvider(): array
    {
        return [
            'org server' => [[]],
            'custom server' => [[Config::XML_PATH_AUTH_SERVER => 'default']],
        ];
    }

    /**
     * @param array<string,mixed> $storeValues
     */
    #[DataProvider('serverTypeProvider')]
    public function testOpenidAndEmailRequestedForEveryServerType(array $storeValues): void
    {
        $scopes = $this->preset($storeValues)->getDefaultScopes();

        self::assertContains('openid', $scopes);
        self::assertContains('email', $scopes);
    }

    /**
     * @param array<string,mixed> $storeValues
     */
    #[DataProvider('serverTypeProvider')]
    public function testGroupsClaimIsGroupsForEveryServerType(array $storeValues): void
    {
        self::assertSame('groups', $this->preset($storeValues)->getGroupsClaim());
    }

    public function testGroupsClaimIsGroups(): void
    {
        self::assertSame('groups', $this->preset->getGroupsClaim());
    }

    public function testBrandingMetadata(): void
    {
        self::assertSame('Sign in with Okta', $this->preset->getButtonLabel());
        self::assertSame(
            'https://magento.loc/static/DmLab_CustomerSsoOkta::images/okta.svg',
            $this->preset->getButtonIconUrl()
        );
    }

    public function testDiscoveryUrlFromOrgDomain(): void
    {
        $url = $this->preset->buildDiscoveryUrl(['domain' => 'dev-123.okta.com']);

        self::assertSame(
            'https://dev-123.okta.com/.well-known/openid-configuration',
            $url
        );
    }

    public function testDiscoveryUrlKeepsExplicitSchemeAndStripsTrailingSlash(): void
    {
        $url = $this->preset->buildDiscoveryUrl(['domain' => 'https://acme.okta.com/']);

        self::assertSame(
            'https://acme.okta.com/.well-known/openid-configuration',
            $url
        );
    }

    public function testDiscoveryUrlWithCustomAuthServer(): void
    {
        $url = $this->preset->buildDiscoveryUrl([
            'domain' => 'dev-123.okta.com',
            'auth_server' => 'default',
        ]);

        self::assertSame(
            'https://dev-123.okta.com/oauth2/default/.well-known/openid-configuration',
            $url
        );
    }

    public function testDiscoveryUrlStripsSlashesAroundAuthServer(): void
    {
        $url = $this->preset->buildDiscoveryUrl([
            'domain' => 'dev-123.okta.com',
            'auth_server' => '/default/',
        ]);

        self::assertSame(
            'https://dev-123.okta.com/oauth2/default/.well-known/openid-configuration',
            $url
        );
    }

    /**
     * An inner slash is encoded so the id cannot escape the `/oauth2/{id}` segment.
     */
    public function testDiscoveryUrlEncodesInnerSlashInAuthServer(): void
    {
        self::assertSame(
            'https://dev-123.okta.com/oauth2/aus%2F..%2Fx/.well-known/openid-configuration',
            $this->preset->buildDiscoveryUrl([
                'domain' => 'dev-123.okta.com',
                'auth_server' => 'aus/../x',
            ])
        );
    }

    public function testDiscoveryUrlIgnoresBlankAuthServer(): void
    {
        $url = $this->preset->buildDiscoveryUrl([
            'domain' => 'dev-123.okta.com',
            'auth_server' => '   ',
        ]);

        self::assertSame(
            'https://dev-123.okta.com/.well-known/openid-configuration',
            $url
        );
    }

    public function testDiscoveryUrlFallsBackToStoredDomain(): void
    {
        $preset = $this->preset([Config::XML_PATH_DOMAIN => 'dev-123.okta.com']);

        self::assertSame(
            'https://dev-123.okta.com/.well-known/openid-configuration',
            $preset->buildDiscoveryUrl([])
        );
    }

    public function testDiscoveryUrlFallsBackToStoredAuthServer(): void
    {
        $preset = $this->preset([
            Config::XML_PATH_DOMAIN => 'dev-123.okta.com',
            Config::XML_PATH_AUTH_SERVER => 'default',
        ]);

        self::assertSame(
            'https://dev-123.okta.com/oauth2/default/.well-known/openid-configuration',
            $preset->buildDiscoveryUrl([])
        );
    }

    public function testExplicitConfigOverridesStoredValues(): void
    {
        $preset = $this->preset([
            Config::XML_PATH_DOMAIN => 'stored.okta.com',
            Config::XML_PATH_AUTH_SERVER => 'stored-server',
        ]);

        self::assertSame(
            'https://explicit.okta.com/.well-known/openid-configuration',
            $preset->buildDiscoveryUrl([
                'domain' => 'explicit.okta.com',
                'auth_server' => '',
            ])
        );
    }

    public function testDiscoveryUrlThrowsWhenDomainMissing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->preset->buildDiscoveryUrl([]);
    }

    public function testDiscoveryUrlThrowsWhenDomainBlank(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->preset->buildDiscoveryUrl(['domain' => '   ']);
    }

    public function testDiscoveryUrlRejectsExplicitHttpDomain(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->preset->buildDiscoveryUrl(['domain' => 'http://dev-123.okta.com']);
    }

    /**
     * A value stored before the backend model existed must still fail closed at login.
     */
    public function testDiscoveryUrlRejectsStoredHttpDomain(): void
    {
        $preset = $this->preset([Config::XML_PATH_DOMAIN => 'http://dev-123.okta.com']);

        $this->expectException(\InvalidArgumentException::class);
        $preset->buildDiscoveryUrl([]);
    }

    public function testDiscoveryUrlKeepsPort(): void
    {
        self::assertSame(
            'https://dev-123.okta.com:8443/.well-known/openid-configuration',
            $this->preset->buildDiscoveryUrl(['domain' => 'https://dev-123.okta.com:8443'])
        );
    }
}
