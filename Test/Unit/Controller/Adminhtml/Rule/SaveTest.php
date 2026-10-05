<?php
declare(strict_types=1);

namespace Panth\ExtraFee\Test\Unit\Controller\Adminhtml\Rule;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Panth\ExtraFee\Api\FeeRuleRepositoryInterface;
use Panth\ExtraFee\Controller\Adminhtml\Rule\Save;
use Panth\ExtraFee\Model\FeeRule;
use Panth\ExtraFee\Model\FeeRuleFactory;
use Panth\ExtraFee\Test\Unit\Controller\Adminhtml\ControllerTestCase;

class SaveTest extends ControllerTestCase
{
    private array $savedData = [];

    private array $persisted = [];

    private array $cleared = [];

    private ?\Exception $saveError = null;

    private function controller(array $params, $post, array $existing = []): Save
    {
        $repository = $this->createStub(FeeRuleRepositoryInterface::class);
        $repository->method('getById')->willReturnCallback(function (int $id) use ($existing) {
            if (!isset($existing[$id])) {
                throw new NoSuchEntityException(__('The fee rule with ID "%1" does not exist.', $id));
            }
            return $this->newWithoutConstructor(FeeRule::class, $existing[$id]);
        });
        $repository->method('save')->willReturnCallback(function (FeeRule $rule) {
            if ($this->saveError) {
                throw $this->saveError;
            }
            if (!$rule->getRuleId()) {
                $rule->setData('rule_id', 50);
            }
            $this->savedData = $rule->getData();
            return $rule;
        });

        $factory = $this->createStub(FeeRuleFactory::class);
        $factory->method('create')->willReturnCallback(fn() => $this->newWithoutConstructor(FeeRule::class));

        $persistor = $this->createStub(DataPersistorInterface::class);
        $persistor->method('set')->willReturnCallback(function ($key, $value) {
            $this->persisted[$key] = $value;
        });
        $persistor->method('clear')->willReturnCallback(function ($key) {
            $this->cleared[] = $key;
        });

        return new Save($this->buildContext($params, $post), $repository, $factory, $persistor);
    }

    public function testEmptyPostRedirectsToGrid(): void
    {
        $this->controller([], [])->execute();

        $this->assertSame('*/*/', $this->redirect['path']);
        $this->assertSame([], $this->savedData);
    }

    public function testNewRuleIsCreatedWithMultiSelectsJoined(): void
    {
        $this->controller([], [
            'rule_id' => '',
            'name' => 'COD',
            'payment_methods' => ['cashondelivery', 'checkmo'],
            'customer_groups' => ['1', '2'],
            'countries' => 'US',
            'regions' => ['5'],
        ])->execute();

        $this->assertSame(50, $this->savedData['rule_id']);
        $this->assertSame('cashondelivery,checkmo', $this->savedData['payment_methods']);
        $this->assertSame('1,2', $this->savedData['customer_groups']);
        $this->assertSame('US', $this->savedData['countries']);
        $this->assertSame(['5'], $this->savedData['regions']);
        $this->assertSame(['The fee rule has been saved.'], $this->messages['success']);
        $this->assertSame(['panth_extra_fee_rule'], $this->cleared);
        $this->assertSame('*/*/', $this->redirect['path']);
    }

    public function testExistingRuleIsMergedAndBackParamReturnsToEdit(): void
    {
        $this->controller(['back' => 'edit'], ['rule_id' => '4', 'fee_amount' => '9'], [
            4 => ['rule_id' => 4, 'name' => 'Old', 'fee_amount' => 1],
        ])->execute();

        $this->assertSame('Old', $this->savedData['name']);
        $this->assertSame('9', $this->savedData['fee_amount']);
        $this->assertSame(['path' => '*/*/edit', 'params' => ['rule_id' => 4]], $this->redirect);
    }

    public function testUnknownRuleKeepsFormDataAndReturnsToEdit(): void
    {
        $this->controller([], ['rule_id' => '9', 'name' => 'Ghost'])->execute();

        $this->assertSame(['The fee rule with ID "9" does not exist.'], $this->messages['error']);
        $this->assertSame('Ghost', $this->persisted['panth_extra_fee_rule']['name']);
        $this->assertSame(['path' => '*/*/edit', 'params' => ['rule_id' => 9]], $this->redirect);
    }

    public function testUnexpectedErrorOnNewRuleReturnsToNewForm(): void
    {
        $this->saveError = new \RuntimeException('db');

        $this->controller([], ['name' => 'Broken', 'store_ids' => ['1']])->execute();

        $this->assertSame(['Something went wrong while saving the fee rule.'], $this->messages['exception']);
        $this->assertSame('1', $this->persisted['panth_extra_fee_rule']['store_ids']);
        $this->assertSame('*/*/new', $this->redirect['path']);
    }

    public function testLocalizedSaveErrorIsShownToTheAdmin(): void
    {
        $this->saveError = new LocalizedException(__('Name is required.'));

        $this->controller([], ['name' => ''])->execute();

        $this->assertSame(['Name is required.'], $this->messages['error']);
        $this->assertSame([], $this->cleared);
    }

    public function testEmptyOptionalFieldsAreStoredAsNullOnUnchangedSave(): void
    {
        $this->controller([], [
            'rule_id' => '4',
            'fee_amount' => '1.5000',
            'min_fee_amount' => '',
            'max_fee_amount' => '',
            'min_order_subtotal' => ' ',
            'max_order_subtotal' => '',
            'min_order_qty' => '',
            'max_order_qty' => '',
            'date_from' => '',
            'date_to' => '',
        ], [
            4 => ['rule_id' => 4, 'name' => 'Handling', 'min_fee_amount' => null, 'max_order_qty' => null],
        ])->execute();

        foreach (['min_fee_amount', 'max_fee_amount', 'min_order_subtotal', 'max_order_subtotal',
            'min_order_qty', 'max_order_qty', 'date_from', 'date_to'] as $field) {
            $this->assertArrayHasKey($field, $this->savedData);
            $this->assertNull($this->savedData[$field], $field);
        }
        $this->assertSame('1.5000', $this->savedData['fee_amount']);
        $this->assertSame(['The fee rule has been saved.'], $this->messages['success']);
    }

    public function testFilledOptionalFieldsAndZeroValuesAreKept(): void
    {
        $this->controller([], [
            'name' => 'Ranges',
            'fee_amount' => '0',
            'fee_amount_percent' => '2.5',
            'min_fee_amount' => '1',
            'max_fee_amount' => '1',
            'min_order_subtotal' => '0',
            'max_order_subtotal' => '500',
            'min_order_qty' => '2',
            'max_order_qty' => '0',
            'date_from' => '2026-01-01',
            'date_to' => '2026-01-01',
        ])->execute();

        $this->assertSame('1', $this->savedData['min_fee_amount']);
        $this->assertSame('500', $this->savedData['max_order_subtotal']);
        $this->assertSame('0', $this->savedData['max_order_qty']);
        $this->assertSame('2026-01-01', $this->savedData['date_to']);
        $this->assertSame([], $this->messages['error']);
    }

    public static function invalidDataProvider(): array
    {
        return [
            'negative fee' => [['fee_amount' => '-5'], 'Fee Amount must be a number of 0 or more.'],
            'negative percent' => [['fee_amount_percent' => '-1'], 'Fee Amount Percent must be a number of 0 or more.'],
            'text amount' => [['max_fee_amount' => 'abc'], 'Maximum Fee Amount must be a number of 0 or more.'],
            'negative qty' => [['min_order_qty' => '-2'], 'Minimum Order Qty must be a number of 0 or more.'],
            'fee range' => [
                ['min_fee_amount' => '10', 'max_fee_amount' => '5'],
                'Minimum Fee Amount cannot be greater than Maximum Fee Amount.',
            ],
            'subtotal range' => [
                ['min_order_subtotal' => '200', 'max_order_subtotal' => '100'],
                'Minimum Order Subtotal cannot be greater than Maximum Order Subtotal.',
            ],
            'qty range' => [
                ['min_order_qty' => '5', 'max_order_qty' => '3'],
                'Minimum Order Qty cannot be greater than Maximum Order Qty.',
            ],
            'dates' => [
                ['date_from' => '2027-12-31', 'date_to' => '2027-01-01'],
                'From Date cannot be later than To Date.',
            ],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidDataProvider')]
    public function testInvalidValuesAreRejectedAndKeptInTheForm(array $fields, string $message): void
    {
        $this->controller([], ['rule_id' => '4', 'name' => 'Bad'] + $fields, [
            4 => ['rule_id' => 4, 'name' => 'Old'],
        ])->execute();

        $this->assertSame([], $this->savedData);
        $this->assertSame([$message], $this->messages['error']);
        $this->assertSame('Bad', $this->persisted['panth_extra_fee_rule']['name']);
        $this->assertSame(['path' => '*/*/edit', 'params' => ['rule_id' => 4]], $this->redirect);
    }
}
