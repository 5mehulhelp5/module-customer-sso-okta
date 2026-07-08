<?php
/**
 * Copyright © MageDevGroup. All rights reserved.
 */
declare(strict_types=1);

namespace MageDevGroup\CustomerSsoOkta\Model;

use MageDevGroup\SsoCore\Api\ProviderPresetInterface;
use Magento\Framework\View\Asset\Repository as AssetRepository;

/**
 * Okta OIDC preset for the customer-sso capability.
 *
 * Okta is standard OIDC, so this preset only describes it: how to build the
 * discovery URL from the org domain (optionally scoped to a custom authorization
 * server), which scopes and groups claim to use, and the login-button branding.
 * All protocol work lives in `sso-core`, all storefront logic in `customer-sso`;
 * a plugin registers this preset into `customer-sso`'s PresetRegistry.
 *
 * The org domain and optional custom authorization server come from the
 * storefront config ({@see Config}); an explicit value in the config array passed
 * to {@see buildDiscoveryUrl} overrides the stored one, keeping the URL a pure
 * function of its inputs where the caller supplies them.
 */
class OktaPreset implements ProviderPresetInterface
{
    /** Stable machine code identifying Okta across the suite. */
    private const CODE = 'okta';

    /** Config key carrying the Okta org domain (e.g. `dev-123.okta.com`). */
    public const CONFIG_DOMAIN = 'domain';

    /** Config key carrying the optional custom authorization server id. */
    public const CONFIG_AUTH_SERVER = 'auth_server';

    /** Login-button label shown on the storefront login page. */
    private const BUTTON_LABEL = 'Sign in with Okta';

    /** Module-relative asset id of the login-button logo. */
    private const ICON_ASSET = 'MageDevGroup_CustomerSsoOkta::images/okta.svg';

    /**
     * @param Config $config
     * @param AssetRepository $assetRepository
     */
    public function __construct(
        private readonly Config $config,
        private readonly AssetRepository $assetRepository
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getCode(): string
    {
        return self::CODE;
    }

    /**
     * @inheritDoc
     */
    public function getLabel(): string
    {
        return 'Okta';
    }

    /**
     * Build Okta's OIDC discovery URL from the org domain.
     *
     * With no custom authorization server this is the org server
     * (`https://{domain}/.well-known/openid-configuration`); with one it is
     * `https://{domain}/oauth2/{authServer}/.well-known/openid-configuration`.
     * The domain may be given with or without a scheme; `https` is assumed.
     *
     * The org domain and auth server fall back to the storefront config when the
     * passed array omits them, so the core can call this with only its generic
     * fields and still get an Okta-configured URL.
     *
     * @param array<string,mixed> $config
     * @throws \InvalidArgumentException when the org domain is missing.
     */
    public function buildDiscoveryUrl(array $config): string
    {
        $domain = $this->normalizeDomain(
            $this->resolve($config, self::CONFIG_DOMAIN, $this->config->getDomain())
        );
        if ($domain === '') {
            throw new \InvalidArgumentException('The Okta org domain is not configured.');
        }

        $authServer = trim(
            $this->resolve($config, self::CONFIG_AUTH_SERVER, $this->config->getAuthServer()),
            " \t/"
        );
        $prefix = $authServer === '' ? $domain : $domain . '/oauth2/' . rawurlencode($authServer);

        return $prefix . '/.well-known/openid-configuration';
    }

    /**
     * @inheritDoc
     */
    public function getDefaultScopes(): array
    {
        return ['openid', 'profile', 'email', 'groups'];
    }

    /**
     * @inheritDoc
     */
    public function getGroupsClaim(): ?string
    {
        return 'groups';
    }

    /**
     * @inheritDoc
     */
    public function getButtonLabel(): string
    {
        return self::BUTTON_LABEL;
    }

    /**
     * @inheritDoc
     *
     * Resolves the shipped Okta logo through the asset repository so the URL is
     * theme/area-correct on the storefront login page.
     */
    public function getButtonIconUrl(): ?string
    {
        return $this->assetRepository->getUrl(self::ICON_ASSET);
    }

    /**
     * Resolve a config value: an explicit key in the passed array wins (even if
     * blank, so a caller can intentionally clear it), otherwise fall back to the
     * stored storefront config value.
     *
     * @param array<string,mixed> $config
     * @param string $key
     * @param string|null $fallback
     */
    private function resolve(array $config, string $key, ?string $fallback): string
    {
        if (array_key_exists($key, $config)) {
            return (string)$config[$key];
        }

        return (string)($fallback ?? '');
    }

    /**
     * Normalize the configured org domain to a scheme-qualified origin with no
     * trailing slash. A bare host gets `https://`; an empty value stays empty so
     * the caller can reject it.
     *
     * @param string $domain
     */
    private function normalizeDomain(string $domain): string
    {
        $domain = trim($domain);
        if ($domain === '') {
            return '';
        }

        if (!preg_match('#^https?://#i', $domain)) {
            $domain = 'https://' . $domain;
        }

        return rtrim($domain, '/');
    }
}
