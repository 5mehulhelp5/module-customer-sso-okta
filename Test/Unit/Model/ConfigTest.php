<?php
/**
 * Copyright © DMLab. All rights reserved.
 */
declare(strict_types=1);

namespace DmLab\CustomerSsoOkta\Test\Unit\Model;

use DmLab\CustomerSsoOkta\Model\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    /**
     * @param array<string,mixed> $values
     */
    private function config(array $values): Config
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')
            ->willReturnCallback(static fn (string $path) => $values[$path] ?? null);

        return new Config($scopeConfig);
    }

    public function testGetDomainTrimsAndTreatsBlankAsNull(): void
    {
        self::assertSame(
            'dev-123.okta.com',
            $this->config([Config::XML_PATH_DOMAIN => '  dev-123.okta.com '])->getDomain()
        );
        self::assertNull($this->config([Config::XML_PATH_DOMAIN => '   '])->getDomain());
        self::assertNull($this->config([])->getDomain());
    }

    public function testGetAuthServerTrimsAndTreatsBlankAsNull(): void
    {
        self::assertSame(
            'default',
            $this->config([Config::XML_PATH_AUTH_SERVER => ' default '])->getAuthServer()
        );
        self::assertNull($this->config([Config::XML_PATH_AUTH_SERVER => '   '])->getAuthServer());
        self::assertNull($this->config([])->getAuthServer());
    }

    public function testReadsStoreScopeAndForwardsStoreId(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->expects(self::once())
            ->method('getValue')
            ->with(Config::XML_PATH_DOMAIN, ScopeInterface::SCOPE_STORE, 7)
            ->willReturn('dev-123.okta.com');

        self::assertSame('dev-123.okta.com', (new Config($scopeConfig))->getDomain(7));
    }

    public function testConfigPathsAreStoreScopedUnderCustomerSso(): void
    {
        self::assertSame('dmlab_customer_sso/okta/domain', Config::XML_PATH_DOMAIN);
        self::assertSame('dmlab_customer_sso/okta/auth_server', Config::XML_PATH_AUTH_SERVER);
    }
}
