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
}
