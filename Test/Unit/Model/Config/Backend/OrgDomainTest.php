<?php
/**
 * Copyright © DMLab. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0). See LICENSE.
 */
declare(strict_types=1);

namespace DmLab\CustomerSsoOkta\Test\Unit\Model\Config\Backend;

use DmLab\CustomerSsoOkta\Model\Config\Backend\OrgDomain;
use DmLab\CustomerSsoOkta\Model\OrgDomain as OrgDomainNormalizer;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use PHPUnit\Framework\TestCase;

class OrgDomainTest extends TestCase
{
    private function backendModel(): OrgDomain
    {
        $context = $this->createStub(Context::class);
        $context->method('getEventDispatcher')->willReturn($this->createStub(ManagerInterface::class));

        return new OrgDomain(
            $context,
            $this->createStub(Registry::class),
            $this->createStub(ScopeConfigInterface::class),
            $this->createStub(TypeListInterface::class),
            new OrgDomainNormalizer(),
            $this->createStub(AbstractResource::class),
            $this->createStub(AbstractDb::class),
            []
        );
    }

    public function testSaveNormalizesDomain(): void
    {
        $model = $this->backendModel();
        $model->setValue('dev-123.okta.com/');
        $model->beforeSave();

        self::assertSame('https://dev-123.okta.com', $model->getValue());
    }

    public function testSaveKeepsBlankDomain(): void
    {
        $model = $this->backendModel();
        $model->setValue('  ');
        $model->beforeSave();

        self::assertSame('', $model->getValue());
    }

    public function testSaveRejectsHttpDomain(): void
    {
        $model = $this->backendModel();
        $model->setValue('http://dev-123.okta.com');

        try {
            $model->beforeSave();
            self::fail('Expected LocalizedException');
        } catch (LocalizedException $e) {
            self::assertSame(
                'The Okta org domain must be a bare host name or an https:// URL with no path.',
                $e->getMessage()
            );
            self::assertInstanceOf(\InvalidArgumentException::class, $e->getPrevious());
        }
    }

    public function testSaveRejectsPath(): void
    {
        $model = $this->backendModel();
        $model->setValue('https://dev-123.okta.com/oauth2/default');

        $this->expectException(LocalizedException::class);
        $model->beforeSave();
    }
}
