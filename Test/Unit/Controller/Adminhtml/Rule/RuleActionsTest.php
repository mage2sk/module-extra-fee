<?php
declare(strict_types=1);

namespace Panth\ExtraFee\Test\Unit\Controller\Adminhtml\Rule;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Page\Config as PageConfig;
use Magento\Framework\View\Page\Title;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Magento\Ui\Component\MassAction\Filter;
use Panth\ExtraFee\Api\FeeRuleRepositoryInterface;
use Panth\ExtraFee\Controller\Adminhtml\Rule\Delete;
use Panth\ExtraFee\Controller\Adminhtml\Rule\Edit;
use Panth\ExtraFee\Controller\Adminhtml\Rule\InlineEdit;
use Panth\ExtraFee\Controller\Adminhtml\Rule\MassDelete;
use Panth\ExtraFee\Controller\Adminhtml\Rule\MassStatus;
use Panth\ExtraFee\Model\FeeRule;
use Panth\ExtraFee\Model\ResourceModel\FeeRule\Collection;
use Panth\ExtraFee\Model\ResourceModel\FeeRule\CollectionFactory;
use Panth\ExtraFee\Test\Unit\Controller\Adminhtml\ControllerTestCase;

class RuleActionsTest extends ControllerTestCase
{
    private function rule(int $id, array $data = []): FeeRule
    {
        return $this->newWithoutConstructor(FeeRule::class, $data + ['rule_id' => $id, 'name' => 'Rule ' . $id]);
    }

    private function collectionFactory(): CollectionFactory
    {
        return $this->factoryReturning(CollectionFactory::class, $this->createStub(Collection::class));
    }

    private function filter(array $rules, bool $throws = false): Filter
    {
        $filter = $this->createStub(Filter::class);
        if ($throws) {
            $filter->method('getCollection')->willThrowException(new LocalizedException(__('Select items first.')));
        } else {
            $filter->method('getCollection')->willReturn($this->collectionOf(Collection::class, $rules));
        }
        return $filter;
    }

    public function testDeleteNeedsAnId(): void
    {
        $repository = $this->createMock(FeeRuleRepositoryInterface::class);
        $repository->expects($this->never())->method('deleteById');

        (new Delete($this->buildContext(), $repository))->execute();

        $this->assertSame(['We cannot find a fee rule to delete.'], $this->messages['error']);
        $this->assertSame('*/*/', $this->redirect['path']);
    }

    public function testDeleteRemovesTheRule(): void
    {
        $repository = $this->createMock(FeeRuleRepositoryInterface::class);
        $repository->expects($this->once())->method('deleteById')->with(4)->willReturn(true);

        (new Delete($this->buildContext(['rule_id' => '4']), $repository))->execute();

        $this->assertSame(['The fee rule has been deleted.'], $this->messages['success']);
    }

    public function testDeleteReportsErrors(): void
    {
        $localized = $this->createStub(FeeRuleRepositoryInterface::class);
        $localized->method('deleteById')->willThrowException(new NoSuchEntityException(__('Gone.')));
        (new Delete($this->buildContext(['rule_id' => 4]), $localized))->execute();
        $this->assertSame(['Gone.'], $this->messages['error']);

        $generic = $this->createStub(FeeRuleRepositoryInterface::class);
        $generic->method('deleteById')->willThrowException(new \RuntimeException('x'));
        (new Delete($this->buildContext(['rule_id' => 4]), $generic))->execute();
        $this->assertSame(['Something went wrong while deleting the fee rule.'], $this->messages['exception']);
    }

    public function testInlineEditRejectsNonAjaxOrEmptyRequests(): void
    {
        $repository = $this->createStub(FeeRuleRepositoryInterface::class);

        (new InlineEdit($this->buildContext(['items' => [1 => []]]), $repository, $this->jsonFactory()))->execute();
        $this->assertTrue($this->json['error']);
        $this->assertSame('Please correct the data sent.', (string)$this->json['messages'][0]);

        (new InlineEdit($this->buildContext(['isAjax' => 1]), $repository, $this->jsonFactory()))->execute();
        $this->assertTrue($this->json['error']);
    }

    public function testInlineEditSavesEachRowAndCollectsErrors(): void
    {
        $saved = [];
        $repository = $this->createStub(FeeRuleRepositoryInterface::class);
        $repository->method('getById')->willReturnCallback(function (int $id) {
            if ($id === 3) {
                throw new NoSuchEntityException(__('No rule 3.'));
            }
            return $this->rule($id, ['fee_amount' => 1]);
        });
        $repository->method('save')->willReturnCallback(function (FeeRule $rule) use (&$saved) {
            if ($rule->getRuleId() === 2) {
                throw new \RuntimeException('db');
            }
            $saved[$rule->getRuleId()] = $rule->getData();
            return $rule;
        });

        (new InlineEdit(
            $this->buildContext(['isAjax' => 1, 'items' => [
                1 => ['fee_amount' => '5'],
                2 => ['fee_amount' => '6'],
                3 => ['fee_amount' => '7'],
            ]]),
            $repository,
            $this->jsonFactory()
        ))->execute();

        $this->assertSame('5', $saved[1]['fee_amount']);
        $this->assertSame('Rule 1', $saved[1]['name']);
        $this->assertTrue($this->json['error']);
        $this->assertSame(
            ['[Rule ID: 2] Something went wrong while saving.', '[Rule ID: 3] No rule 3.'],
            array_map('strval', $this->json['messages'])
        );
    }

    public function testMassDeleteCountsSuccessesAndFailures(): void
    {
        $repository = $this->createStub(FeeRuleRepositoryInterface::class);
        $repository->method('delete')->willReturnCallback(static function (FeeRule $rule) {
            if ($rule->getRuleId() === 3) {
                throw new \RuntimeException('locked');
            }
            return true;
        });

        (new MassDelete(
            $this->buildContext(),
            $this->filter([$this->rule(1), $this->rule(2), $this->rule(3)]),
            $this->collectionFactory(),
            $repository
        ))->execute();

        $this->assertSame(['2 fee rules have been deleted.'], $this->messages['success']);
        $this->assertSame(['1 fee rule could not be deleted.'], $this->messages['error']);
        $this->assertSame('*/*/', $this->redirect['path']);
    }

    public function testMassDeleteSingularMessagesAndFilterErrors(): void
    {
        $repository = $this->createStub(FeeRuleRepositoryInterface::class);
        $repository->method('delete')->willReturn(true);

        (new MassDelete($this->buildContext(), $this->filter([$this->rule(1)]), $this->collectionFactory(), $repository))
            ->execute();
        $this->assertSame(['1 fee rule has been deleted.'], $this->messages['success']);

        (new MassDelete($this->buildContext(), $this->filter([], true), $this->collectionFactory(), $repository))
            ->execute();
        $this->assertSame(['Select items first.'], $this->messages['error']);
    }

    public function testMassStatusTogglesActiveFlag(): void
    {
        $states = [];
        $repository = $this->createStub(FeeRuleRepositoryInterface::class);
        $repository->method('save')->willReturnCallback(static function (FeeRule $rule) use (&$states) {
            if ($rule->getRuleId() === 9) {
                throw new \RuntimeException('x');
            }
            $states[$rule->getRuleId()] = $rule->getIsActive();
            return $rule;
        });

        (new MassStatus(
            $this->buildContext(['status' => '0']),
            $this->filter([$this->rule(1, ['is_active' => 1]), $this->rule(2, ['is_active' => 1]), $this->rule(9), $this->rule(8)]),
            $this->collectionFactory(),
            $repository
        ))->execute();

        $this->assertSame([1 => false, 2 => false, 8 => false], $states);
        $this->assertSame(['3 fee rules have been disabled.'], $this->messages['success']);
        $this->assertSame(['1 fee rule could not be updated.'], $this->messages['error']);
    }

    public function testMassStatusEnableSingleAndFilterError(): void
    {
        $repository = $this->createStub(FeeRuleRepositoryInterface::class);
        $repository->method('save')->willReturnArgument(0);

        (new MassStatus($this->buildContext(['status' => '1']), $this->filter([$this->rule(1)]), $this->collectionFactory(), $repository))
            ->execute();
        $this->assertSame(['1 fee rule has been enabled.'], $this->messages['success']);

        (new MassStatus($this->buildContext(['status' => '1']), $this->filter([], true), $this->collectionFactory(), $repository))
            ->execute();
        $this->assertSame(['Select items first.'], $this->messages['error']);
    }

    private function pageFactory(array &$titles): PageFactory
    {
        $title = $this->createStub(Title::class);
        $title->method('prepend')->willReturnCallback(static function ($value) use (&$titles) {
            $titles[] = (string)$value;
        });
        $config = $this->createStub(PageConfig::class);
        $config->method('getTitle')->willReturn($title);
        $page = $this->createStub(Page::class);
        $page->method('getConfig')->willReturn($config);
        $page->method('setActiveMenu')->willReturnSelf();
        $factory = $this->createStub(PageFactory::class);
        $factory->method('create')->willReturn($page);
        return $factory;
    }

    public function testEditTitlesNewAndExistingRules(): void
    {
        $titles = [];
        $repository = $this->createStub(FeeRuleRepositoryInterface::class);
        $repository->method('getById')->willReturn($this->rule(4, ['name' => 'COD fee']));

        $result = (new Edit($this->buildContext(), $this->pageFactory($titles), $repository))->execute();
        (new Edit($this->buildContext(['rule_id' => 4]), $this->pageFactory($titles), $repository))->execute();

        $this->assertInstanceOf(Page::class, $result);
        $this->assertSame(['New Fee Rule', 'Edit Fee Rule: COD fee'], $titles);
    }

    public function testEditRedirectsWhenRuleIsGone(): void
    {
        $titles = [];
        $repository = $this->createStub(FeeRuleRepositoryInterface::class);
        $repository->method('getById')->willThrowException(new NoSuchEntityException(__('x')));

        (new Edit($this->buildContext(['rule_id' => 4]), $this->pageFactory($titles), $repository))->execute();

        $this->assertSame(['This fee rule no longer exists.'], $this->messages['error']);
        $this->assertSame('*/*/', $this->redirect['path']);
        $this->assertSame([], $titles);
    }
}
