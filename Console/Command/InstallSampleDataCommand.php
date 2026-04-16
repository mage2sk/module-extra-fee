<?php

declare(strict_types=1);

namespace Panth\ExtraFee\Console\Command;

use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Panth\ExtraFee\Model\FeeRuleFactory;
use Panth\ExtraFee\Model\ResourceModel\FeeRule as FeeRuleResource;
use Panth\ExtraFee\Model\ResourceModel\FeeRule\CollectionFactory as FeeRuleCollectionFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class InstallSampleDataCommand extends Command
{
    /**
     * @var FeeRuleFactory
     */
    private FeeRuleFactory $feeRuleFactory;

    /**
     * @var FeeRuleResource
     */
    private FeeRuleResource $feeRuleResource;

    /**
     * @var FeeRuleCollectionFactory
     */
    private FeeRuleCollectionFactory $collectionFactory;

    /**
     * @var State
     */
    private State $state;

    /**
     * @param FeeRuleFactory $feeRuleFactory
     * @param FeeRuleResource $feeRuleResource
     * @param FeeRuleCollectionFactory $collectionFactory
     * @param State $state
     * @param string|null $name
     */
    public function __construct(
        FeeRuleFactory $feeRuleFactory,
        FeeRuleResource $feeRuleResource,
        FeeRuleCollectionFactory $collectionFactory,
        State $state,
        ?string $name = null
    ) {
        $this->feeRuleFactory = $feeRuleFactory;
        $this->feeRuleResource = $feeRuleResource;
        $this->collectionFactory = $collectionFactory;
        $this->state = $state;
        parent::__construct($name);
    }

    /**
     * @inheritdoc
     */
    protected function configure(): void
    {
        $this->setName('panth:extrafee:install-sample-data');
        $this->setDescription('Install sample fee rules for Panth ExtraFee module');
        parent::configure();
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->state->setAreaCode(\Magento\Framework\App\Area::AREA_ADMINHTML);
        } catch (LocalizedException $e) {
            // Area code already set
        }

        $output->writeln('<info>Installing Panth ExtraFee sample data...</info>');
        $output->writeln('');

        $sampleRules = $this->getSampleRules();
        $existingNames = $this->getExistingRuleNames();
        $created = 0;
        $skipped = 0;

        foreach ($sampleRules as $ruleData) {
            $ruleName = $ruleData['name'];

            if (in_array($ruleName, $existingNames, true)) {
                $output->writeln(
                    sprintf('<comment>  [SKIP] Rule "%s" already exists.</comment>', $ruleName)
                );
                $skipped++;
                continue;
            }

            try {
                $feeRule = $this->feeRuleFactory->create();
                $feeRule->setData($ruleData);
                $this->feeRuleResource->save($feeRule);
                $output->writeln(
                    sprintf('<info>  [OK]   Created rule "%s" (ID: %s)</info>', $ruleName, $feeRule->getId())
                );
                $created++;
            } catch (\Exception $e) {
                $output->writeln(
                    sprintf('<error>  [ERR]  Failed to create rule "%s": %s</error>', $ruleName, $e->getMessage())
                );
            }
        }

        $output->writeln('');
        $output->writeln(sprintf(
            '<info>Done. Created: %d | Skipped: %d | Total rules: %d</info>',
            $created,
            $skipped,
            count($sampleRules)
        ));

        return Command::SUCCESS;
    }

    /**
     * Get existing rule names to avoid duplicates
     *
     * @return array
     */
    private function getExistingRuleNames(): array
    {
        $collection = $this->collectionFactory->create();
        $names = [];
        foreach ($collection as $rule) {
            $names[] = $rule->getName();
        }
        return $names;
    }

    /**
     * Get sample rule data
     *
     * @return array[]
     */
    private function getSampleRules(): array
    {
        return [
            [
                'name' => 'Payment Processing Fee',
                'fee_label' => 'Payment Processing Fee',
                'fee_type' => 'percent',
                'fee_amount' => 2.5,
                'apply_per' => 'order',
                'is_active' => 1,
                'payment_methods' => 'cashondelivery,banktransfer',
                'sort_order' => 10,
                'description' => 'Applies a 2.5% processing fee for Cash on Delivery and Bank Transfer payments.',
            ],
            [
                'name' => 'Small Order Handling Fee',
                'fee_label' => 'Small Order Handling Fee',
                'fee_type' => 'fixed',
                'fee_amount' => 5.00,
                'apply_per' => 'order',
                'max_order_subtotal' => 30.00,
                'is_active' => 1,
                'sort_order' => 20,
                'description' => 'Adds a $5 handling fee for orders with subtotal below $30.',
            ],
            [
                'name' => 'International Shipping Surcharge',
                'fee_label' => 'International Shipping Surcharge',
                'fee_type' => 'fixed',
                'fee_amount' => 15.00,
                'apply_per' => 'order',
                'is_active' => 1,
                'countries' => 'IN,BR,MX,JP,CN',
                'sort_order' => 30,
                'description' => 'Adds a $15 surcharge for orders shipping to IN, BR, MX, JP, CN.',
            ],
            [
                'name' => 'Bulk Order Processing',
                'fee_label' => 'Bulk Order Processing Fee',
                'fee_type' => 'fixed_minimum',
                'fee_amount' => 10.00,
                'fee_amount_percent' => 1.5,
                'apply_per' => 'order',
                'is_active' => 1,
                'min_order_qty' => 10,
                'customer_groups' => '2',
                'sort_order' => 40,
                'description' => 'Wholesale bulk order processing: 1.5% of subtotal with a $10 minimum. Applies to wholesale customer group with 10+ items.',
            ],
            [
                'name' => 'Premium Product Handling Fee',
                'fee_label' => 'Premium Product Handling',
                'fee_type' => 'fixed',
                'fee_amount' => 3.00,
                'apply_per' => 'product',
                'is_active' => 1,
                'category_ids' => '3,4',
                'sort_order' => 50,
                'description' => 'Adds a $3 per-product handling fee for items in premium categories.',
            ],
            [
                'name' => 'Cash on Delivery Extra Charge',
                'fee_label' => 'COD Processing Fee',
                'fee_type' => 'combined',
                'fee_amount' => 2.00,
                'fee_amount_percent' => 1.0,
                'apply_per' => 'order',
                'is_active' => 1,
                'payment_methods' => 'cashondelivery',
                'sort_order' => 15,
                'tax_class_id' => 2,
                'description' => 'Combined COD fee: $2 flat + 1% of order subtotal. Taxable under tax class ID 2.',
            ],
            [
                'name' => 'Express Weekend Processing',
                'fee_label' => 'Weekend Processing Fee',
                'fee_type' => 'fixed',
                'fee_amount' => 7.50,
                'apply_per' => 'order',
                'is_active' => 0,
                'sort_order' => 60,
                'description' => 'Optional weekend processing fee ($7.50). Disabled by default — enable in admin when needed.',
            ],
            [
                'name' => 'Order Insurance Fee',
                'fee_label' => 'Order Insurance',
                'fee_type' => 'percent',
                'fee_amount' => 0.5,
                'apply_per' => 'order',
                'is_active' => 1,
                'min_order_subtotal' => 500.00,
                'is_refundable' => 1,
                'sort_order' => 70,
                'description' => 'Adds 0.5% insurance fee for orders with subtotal $500+. This fee is refundable.',
            ],
        ];
    }
}
