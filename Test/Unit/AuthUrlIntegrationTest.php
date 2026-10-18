<?php
/**
 * Copyright © DMLab. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0). See LICENSE.
 */
declare(strict_types=1);

namespace DmLab\CustomerSsoOkta\Test\Unit;

use DmLab\CustomerSso\Model\ActiveProviderResolver;
use DmLab\CustomerSso\Model\Config as CustomerSsoConfig;
use DmLab\CustomerSso\Model\Config\Source\ActiveProvider;
use DmLab\CustomerSso\Model\Oidc\AuthorizationStarter;
use DmLab\CustomerSso\Model\PresetRegistry;
use DmLab\CustomerSso\Model\Session\SsoIntentStorage;
use DmLab\CustomerSso\Model\SsoIntent;
use DmLab\CustomerSsoOkta\Model\Config as OktaConfig;
use DmLab\CustomerSsoOkta\Model\OktaPreset;
use DmLab\CustomerSsoOkta\Model\OrgDomain;
use DmLab\SsoCore\Api\AuthorizationStateStorageInterface;
use DmLab\SsoCore\Model\Oidc\AuthorizationRequestFactory;
use DmLab\SsoCore\Model\Oidc\DiscoveryClient;
use DmLab\SsoCore\Model\Oidc\ProviderMetadata;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Math\Random;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Asset\Repository as AssetRepository;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end acceptance check (Task 5): with the Okta preset wired into
 * customer-sso's registry (as di.xml does), Okta must surface in the storefront
 * provider dropdown and drive the OIDC authorization URL. Discovery and the
 * customer-sso config are stubbed; the Okta-specific pieces (registry entry,
 * discovery URL from org domain, default scopes) are real.
 */
class AuthUrlIntegrationTest extends TestCase
{
    private const OKTA_DOMAIN = 'dev-123.okta.com';
    private const AUTH_ENDPOINT = 'https://dev-123.okta.com/oauth2/v1/authorize';

    private function buildRegistry(string $authServer = ''): PresetRegistry
    {
        $oktaScopeConfig = $this->createStub(ScopeConfigInterface::class);
        $oktaScopeConfig->method('getValue')->willReturnCallback(
            static fn (string $path): ?string => match ($path) {
                OktaConfig::XML_PATH_DOMAIN => self::OKTA_DOMAIN,
                OktaConfig::XML_PATH_AUTH_SERVER => $authServer,
                default => null,
            }
        );

        $assetRepository = $this->createStub(AssetRepository::class);
        $assetRepository->method('getUrl')->willReturn('https://magento.loc/okta.svg');

        $preset = new OktaPreset(new OktaConfig($oktaScopeConfig), new OrgDomain(), $assetRepository);

        // Mirrors etc/di.xml: the Okta preset registered under its own code.
        return new PresetRegistry([$preset]);
    }

    public function testOktaAppearsInProviderDropdown(): void
    {
        $source = new ActiveProvider($this->buildRegistry());

        $values = array_column($source->toOptionArray(), 'label', 'value');

        self::assertArrayHasKey('okta', $values, 'Okta is missing from the provider dropdown.');
        self::assertSame('Okta', (string)$values['okta']);
    }

    public function testActiveOktaProviderDrivesAuthorizationUrl(): void
    {
        $authUrl = $this->startAuthorization('', 'https://dev-123.okta.com/.well-known/openid-configuration');

        self::assertStringStartsWith(self::AUTH_ENDPOINT . '?', $authUrl);
        self::assertStringContainsString('client_id=client-xyz', $authUrl);
        // Okta preset's default scopes drive the request (space → %20).
        self::assertStringContainsString('scope=openid%20profile%20email%20groups', $authUrl);
        self::assertStringContainsString('code_challenge_method=S256', $authUrl);
    }

    public function testCustomAuthServerDrivesDiscoveryAndOmitsGroupsScope(): void
    {
        $authUrl = $this->startAuthorization(
            'default',
            'https://dev-123.okta.com/oauth2/default/.well-known/openid-configuration'
        );

        self::assertMatchesRegularExpression('/[?&]scope=openid%20profile%20email(&|$)/', $authUrl);
    }

    private function startAuthorization(string $authServer, string $discoveryUrl): string
    {
        $registry = $this->buildRegistry($authServer);

        // Storefront config selects "okta" as the active provider.
        $customerScopeConfig = $this->createStub(ScopeConfigInterface::class);
        $customerScopeConfig->method('getValue')->willReturnCallback(
            static fn (string $path): ?string =>
                $path === ActiveProviderResolver::XML_PATH_ACTIVE_PROVIDER ? 'okta' : null
        );
        $resolver = new ActiveProviderResolver($customerScopeConfig, $registry);

        self::assertInstanceOf(OktaPreset::class, $resolver->getActive());

        // customer-sso config: SSO enabled with a client id; provider-neutral.
        $config = $this->createStub(CustomerSsoConfig::class);
        $config->method('isEnabled')->willReturn(true);
        $config->method('getClientId')->willReturn('client-xyz');

        // Discovery is stubbed: only the Okta discovery URL feeding it is real.
        $discovery = $this->createMock(DiscoveryClient::class);
        $discovery->expects(self::once())
            ->method('discover')
            ->with($discoveryUrl)
            ->willReturn(new ProviderMetadata(
                'https://dev-123.okta.com',
                self::AUTH_ENDPOINT,
                'https://dev-123.okta.com/oauth2/v1/token',
                'https://dev-123.okta.com/oauth2/v1/keys'
            ));

        $random = $this->createStub(Random::class);
        $random->method('getRandomBytes')->willReturn(str_repeat("\0", 32));

        $stateStorage = $this->createMock(AuthorizationStateStorageInterface::class);
        $stateStorage->expects(self::once())->method('save');

        $intentStorage = $this->createMock(SsoIntentStorage::class);
        $intentStorage->expects(self::once())->method('save');

        $url = $this->createStub(UrlInterface::class);
        $url->method('getUrl')->willReturn('https://magento.loc/customersso/sso/callback');

        $starter = new AuthorizationStarter(
            $config,
            $resolver,
            $discovery,
            new AuthorizationRequestFactory($random),
            $stateStorage,
            $intentStorage,
            $url
        );

        return $starter->start(new SsoIntent(SsoIntent::MODE_LOGIN));
    }
}
