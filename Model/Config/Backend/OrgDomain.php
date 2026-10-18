<?php
/**
 * Copyright © DMLab. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0). See LICENSE.
 */
declare(strict_types=1);

namespace DmLab\CustomerSsoOkta\Model\Config\Backend;

use DmLab\CustomerSsoOkta\Model\OrgDomain as OrgDomainNormalizer;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;

/**
 * Stores the Okta org domain normalized to an https origin; rejects anything else.
 */
class OrgDomain extends Value
{
    /**
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param OrgDomainNormalizer $orgDomain
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        private readonly OrgDomainNormalizer $orgDomain,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * Normalize the domain before it is persisted.
     *
     * @return $this
     * @throws LocalizedException when the domain is not a bare host or an https origin.
     */
    public function beforeSave()
    {
        try {
            $this->setValue($this->orgDomain->normalize((string)$this->getValue()));
        } catch (\InvalidArgumentException $e) {
            throw new LocalizedException(
                __('The Okta org domain must be a bare host name or an https:// URL with no path.'),
                $e
            );
        }

        return parent::beforeSave();
    }
}
