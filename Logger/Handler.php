<?php

declare(strict_types=1);

namespace Panth\ExtraFee\Logger;

use Magento\Framework\Logger\Handler\Base;
use Monolog\Logger;

class Handler extends Base
{
    /**
     * @var int
     */
    protected $loggerType = Logger::DEBUG;

    /**
     * @var string
     */
    protected $fileName = '/var/log/panth_extra_fee.log';
}
