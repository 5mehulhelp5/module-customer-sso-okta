<?php
/**
 * Copyright © DMLab. All rights reserved.
 */
declare(strict_types=1);

namespace DmLab\CustomerSsoOkta\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Typed reader over the Okta-specific storefront configuration.
 *
 * Okta is standard OIDC, so the only IdP-specific settings are the org domain and
 * an optional custom authorization server; both live under the customer-sso
 * section (group `okta`). {@see OktaPreset} reads them here to build the discovery
 * URL, keeping the provider-agnostic core free of Okta config paths. Reads are
 * store-scoped like the rest of customer-sso: storefront SSO can be tuned per
 * store view.
 */
class Config
{
    /** Okta org domain (e.g. `dev-123.okta.com`). */
    public const XML_PATH_DOMAIN = 'dmlab_customer_sso/okta/domain';

    /** Optional custom authorization server id (e.g. `default`). */
    public const XML_PATH_AUTH_SERVER = 'dmlab_customer_sso/okta/auth_server';

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * Configured Okta org domain for the given store, or null when unset.
     *
     * @param int|string|null $storeId
     */
    public function getDomain($storeId = null): ?string
    {
        return $this->readNonEmptyString(self::XML_PATH_DOMAIN, $storeId);
    }

    /**
     * Configured custom authorization server id, or null when unset (org server).
     *
     * @param int|string|null $storeId
     */
    public function getAuthServer($storeId = null): ?string
    {
        return $this->readNonEmptyString(self::XML_PATH_AUTH_SERVER, $storeId);
    }

    /**
     * Read a store-scoped config value as a trimmed non-empty string, or null.
     *
     * @param string $path
     * @param int|string|null $storeId
     */
    private function readNonEmptyString(string $path, $storeId = null): ?string
    {
        $value = $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId);
        $value = is_string($value) ? trim($value) : '';

        return $value === '' ? null : $value;
    }
}
