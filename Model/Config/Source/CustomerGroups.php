<?php

declare(strict_types=1);

namespace Panth\ExtraFee\Model\Config\Source;

use Magento\Customer\Model\ResourceModel\Group\CollectionFactory as GroupCollectionFactory;
use Magento\Framework\Data\OptionSourceInterface;

class CustomerGroups implements OptionSourceInterface
{
    /**
     * @var GroupCollectionFactory
     */
    private GroupCollectionFactory $groupCollectionFactory;

    /**
     * @var array|null
     */
    private ?array $options = null;

    /**
     * @param GroupCollectionFactory $groupCollectionFactory
     */
    public function __construct(GroupCollectionFactory $groupCollectionFactory)
    {
        $this->groupCollectionFactory = $groupCollectionFactory;
    }

    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        if ($this->options === null) {
            $this->options = $this->groupCollectionFactory->create()->toOptionArray();
        }

        return $this->options;
    }
}
