<?php
/**
 * Copyright © DMLab. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0). See LICENSE.
 */
declare(strict_types=1);

namespace DmLab\CustomerSsoOkta\Test\Unit\Model;

use DmLab\CustomerSsoOkta\Model\OrgDomain;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class OrgDomainTest extends TestCase
{
    /**
     * @return array<string,array{string,string}>
     */
    public static function validProvider(): array
    {
        return [
            'bare host' => ['dev-123.okta.com', 'https://dev-123.okta.com'],
            'https kept' => ['https://dev-123.okta.com', 'https://dev-123.okta.com'],
            'trailing slash stripped' => ['https://dev-123.okta.com/', 'https://dev-123.okta.com'],
            'bare host trailing slash' => ['dev-123.okta.com/', 'https://dev-123.okta.com'],
            'port kept' => ['https://dev-123.okta.com:8443', 'https://dev-123.okta.com:8443'],
            'bare host with port' => ['dev-123.okta.com:8443', 'https://dev-123.okta.com:8443'],
            'case-insensitive scheme' => ['HTTPS://dev-123.okta.com', 'https://dev-123.okta.com'],
            'surrounding whitespace' => ['  login.acme.com  ', 'https://login.acme.com'],
            'host lowercased' => ['HTTPS://Dev-123.OKTA.com', 'https://dev-123.okta.com'],
            'default port dropped' => ['https://dev-123.okta.com:443', 'https://dev-123.okta.com'],
        ];
    }

    #[DataProvider('validProvider')]
    public function testNormalizesValidDomain(string $input, string $expected): void
    {
        self::assertSame($expected, (new OrgDomain())->normalize($input));
    }

    public function testBlankStaysBlank(): void
    {
        self::assertSame('', (new OrgDomain())->normalize('   '));
    }

    /**
     * @return array<string,array{string,string}>
     */
    public static function invalidProvider(): array
    {
        $https = 'must use https';
        $host = 'must be a host name';

        return [
            'http' => ['http://dev-123.okta.com', $https],
            'http uppercase' => ['HTTP://dev-123.okta.com', $https],
            'ftp' => ['ftp://dev-123.okta.com', $https],
            'path' => ['https://dev-123.okta.com/oauth2/default', $host],
            'bare host with path' => ['dev-123.okta.com/evil', $host],
            'query' => ['https://dev-123.okta.com?x=1', $host],
            'empty query' => ['https://dev-123.okta.com?', $host],
            'fragment' => ['https://dev-123.okta.com#x', $host],
            'userinfo' => ['https://user:pass@dev-123.okta.com', $host],
            'user only' => ['user@dev-123.okta.com', $host],
            'no host' => ['https://', $host],
            'invalid host chars' => ['dev_123 .okta.com', $host],
            'empty label' => ['dev..okta.com', $host],
            'trailing dot' => ['dev-123.okta.com.', $host],
            'port above range' => ['dev-123.okta.com:65536', $host],
            'port zero' => ['https://dev-123.okta.com:0', 'invalid port'],
        ];
    }

    #[DataProvider('invalidProvider')]
    public function testRejectsInvalidDomain(string $input, string $reason): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($reason);
        (new OrgDomain())->normalize($input);
    }
}
