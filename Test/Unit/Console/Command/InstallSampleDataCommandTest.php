<?php
declare(strict_types=1);

namespace Panth\ExtraFee\Test\Unit\Console\Command;

use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Panth\ExtraFee\Console\Command\InstallSampleDataCommand;
use Panth\ExtraFee\Model\FeeRule;
use Panth\ExtraFee\Model\FeeRuleFactory;
use Panth\ExtraFee\Model\ResourceModel\FeeRule as FeeRuleResource;
use Panth\ExtraFee\Model\ResourceModel\FeeRule\Collection;
use Panth\ExtraFee\Model\ResourceModel\FeeRule\CollectionFactory;
use Panth\ExtraFee\Test\Unit\Fixture\ObjectHelperTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class InstallSampleDataCommandTest extends TestCase
{
    use ObjectHelperTrait;

    private array $saved = [];

    private ?InstallSampleDataCommand $command = null;

    private function tester(array $existingNames, ?string $failingName = null): CommandTester
    {
        $resource = $this->createStub(FeeRuleResource::class);
        $resource->method('save')->willReturnCallback(function (FeeRule $rule) use ($resource, $failingName) {
            if ($rule->getName() === $failingName) {
                throw new \RuntimeException('constraint violation');
            }
            $rule->setData('rule_id', count($this->saved) + 1);
            $this->saved[] = $rule->getData();
            return $resource;
        });
        $factory = $this->createStub(FeeRuleFactory::class);
        $factory->method('create')->willReturnCallback(function () {
            $rule = $this->newWithoutConstructor(FeeRule::class);
            $rule->setIdFieldName('rule_id');
            return $rule;
        });
        $existing = array_map(
            fn($name) => $this->newWithoutConstructor(FeeRule::class, ['name' => $name]),
            $existingNames
        );
        $state = $this->createStub(State::class);
        $state->method('setAreaCode')->willThrowException(new LocalizedException(__('Area code is already set')));

        $this->command = new InstallSampleDataCommand(
            $factory,
            $resource,
            $this->factoryReturning(CollectionFactory::class, $this->collectionOf(Collection::class, $existing)),
            $state
        );

        return new CommandTester($this->command);
    }

    public function testCommandIsNamed(): void
    {
        $this->tester([]);

        $this->assertSame('panth:extrafee:install-sample-data', $this->command->getName());
        $this->assertNotSame('', $this->command->getDescription());
    }

    public function testAllSampleRulesAreCreatedOnAFreshInstall(): void
    {
        $tester = $this->tester([]);

        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertCount(8, $this->saved);
        $this->assertStringContainsString('Created: 8 | Skipped: 0 | Total rules: 8', $tester->getDisplay());
        $types = array_values(array_unique(array_column($this->saved, 'fee_type')));
        sort($types);
        $this->assertSame(['combined', 'fixed', 'fixed_minimum', 'percent'], $types);
    }

    public function testExistingRulesAreSkippedByName(): void
    {
        $tester = $this->tester(['Payment Processing Fee', 'Order Insurance Fee']);

        $tester->execute([]);

        $this->assertCount(6, $this->saved);
        $this->assertNotContains('Payment Processing Fee', array_column($this->saved, 'name'));
        $this->assertStringContainsString('[SKIP] Rule "Order Insurance Fee" already exists.', $tester->getDisplay());
        $this->assertStringContainsString('Created: 6 | Skipped: 2', $tester->getDisplay());
    }

    public function testSaveFailuresAreReportedAndDoNotAbort(): void
    {
        $tester = $this->tester([], 'Bulk Order Processing');

        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertCount(7, $this->saved);
        $this->assertStringContainsString(
            '[ERR]  Failed to create rule "Bulk Order Processing": constraint violation',
            $tester->getDisplay()
        );
        $this->assertStringContainsString('Created: 7 | Skipped: 0', $tester->getDisplay());
    }

    public function testSampleRulesUseOnlyKnownApplyPerValues(): void
    {
        $this->tester([])->execute([]);

        foreach ($this->saved as $row) {
            $this->assertContains($row['apply_per'], ['order', 'product', 'quantity']);
        }
    }

    public function testPercentSampleRulesCarryAPercentage(): void
    {
        $this->tester([])->execute([]);

        $percentRows = array_filter($this->saved, fn($row) => $row['fee_type'] === 'percent');
        $this->assertNotEmpty($percentRows);
        foreach ($percentRows as $row) {
            $this->assertGreaterThan(0, (float)($row['fee_amount_percent'] ?? 0));
        }
    }
}
