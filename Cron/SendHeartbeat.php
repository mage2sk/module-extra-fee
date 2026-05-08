<?php
/**
 * Daily heartbeat cron for Panth_ExtraFee. Skips if Panth_Core is
 * enabled, because Core handles the heartbeat for the whole site (and
 * we don't want every sibling module's cron stampeding the receiver).
 */
declare(strict_types=1);

namespace Panth\ExtraFee\Cron;

use Magento\Framework\Module\Manager as ModuleManager;
use Panth\ExtraFee\Service\InstallReporter;

class SendHeartbeat
{
    public function __construct(
        private readonly InstallReporter $reporter,
        private readonly ModuleManager $moduleManager
    ) {
    }

    public function execute(): void
    {
        if ($this->moduleManager->isEnabled('Panth_Core')) {
            return;
        }
        $this->reporter->reportHeartbeat();
    }
}
