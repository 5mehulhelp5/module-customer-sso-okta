<?php
/**
 * Copyright © DMLab. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0). See LICENSE.
 */
declare(strict_types=1);

namespace DmLab\CustomerSsoOkta\Model;

/**
 * Normalizes the Okta org domain to a lowercase `https://host[:port]` origin.
 *
 * The domain is the trust root for discovery, issuer and JWKS, so only `https`
 * is accepted; anything beyond scheme, host and port is rejected.
 */
class OrgDomain
{
    /**
     * Normalize an org domain; a bare host gets `https://`, blank stays `''`.
     *
     * @param string $value
     * @throws \InvalidArgumentException when the value is not a bare host or an https origin.
     */
    public function normalize(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (!preg_match('#^[a-z][a-z0-9+.\-]*://#i', $value)) {
            $value = 'https://' . $value;
        }

        // phpcs:ignore Magento2.Functions.DiscouragedFunction
        $parts = parse_url($value);
        if ($parts !== false && strtolower($parts['scheme'] ?? '') !== 'https') {
            throw new \InvalidArgumentException('The Okta org domain must use https.');
        }

        $label = '[a-z0-9](?:[a-z0-9\-]*[a-z0-9])?';
        if ($parts === false
            || !preg_match('/^' . $label . '(?:\.' . $label . ')*$/i', $parts['host'] ?? '')
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || !in_array($parts['path'] ?? '', ['', '/'], true)
        ) {
            throw new \InvalidArgumentException(
                'The Okta org domain must be a host name with no path, query, fragment or credentials.'
            );
        }

        $host = $parts['host'];
        // parse_url() already rejects ports above 65535.
        $port = $parts['port'] ?? 443;
        if ($port === 0) {
            throw new \InvalidArgumentException('The Okta org domain has an invalid port.');
        }

        // Lowercase host, default port dropped: discovery compares the issuer as an exact string.
        return 'https://' . strtolower($host) . ($port === 443 ? '' : ':' . $port);
    }
}
