<?php
declare(strict_types=1);

namespace Panth\ExtraFee\Test\Unit\Controller\Adminhtml;

use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Backend\Model\View\Result\RedirectFactory;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\Json as JsonResult;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Message\ManagerInterface;
use Panth\ExtraFee\Test\Unit\Fixture\ObjectHelperTrait;
use PHPUnit\Framework\TestCase;

/**
 * Shared wiring for admin controller tests: records redirects, JSON payloads
 * and flash messages.
 */
abstract class ControllerTestCase extends TestCase
{
    use ObjectHelperTrait;

    protected array $redirect = [];

    protected array $messages = [];

    protected ?array $json = null;

    protected function buildContext(array $params = [], $post = []): Context
    {
        $this->redirect = [];
        $this->messages = ['success' => [], 'error' => [], 'exception' => []];

        $request = $this->createStub(Http::class);
        $request->method('getParam')->willReturnCallback(
            static fn($key, $default = null) => $params[$key] ?? $default
        );
        $request->method('getPostValue')->willReturn($post);

        $redirect = $this->createStub(Redirect::class);
        $redirect->method('setPath')->willReturnCallback(function ($path, $args = []) use (&$redirect) {
            $this->redirect = ['path' => $path, 'params' => $args];
            return $redirect;
        });
        $redirectFactory = $this->createStub(RedirectFactory::class);
        $redirectFactory->method('create')->willReturn($redirect);

        $messageManager = $this->createStub(ManagerInterface::class);
        $messageManager->method('addSuccessMessage')->willReturnCallback(function ($message) use (&$messageManager) {
            $this->messages['success'][] = (string)$message;
            return $messageManager;
        });
        $messageManager->method('addErrorMessage')->willReturnCallback(function ($message) use (&$messageManager) {
            $this->messages['error'][] = (string)$message;
            return $messageManager;
        });
        $messageManager->method('addExceptionMessage')->willReturnCallback(
            function ($exception, $message) use (&$messageManager) {
                $this->messages['exception'][] = (string)$message;
                return $messageManager;
            }
        );

        $context = $this->createStub(Context::class);
        $context->method('getRequest')->willReturn($request);
        $context->method('getResultRedirectFactory')->willReturn($redirectFactory);
        $context->method('getMessageManager')->willReturn($messageManager);

        return $context;
    }

    protected function jsonFactory(): JsonFactory
    {
        $this->json = null;
        $result = $this->createStub(JsonResult::class);
        $result->method('setData')->willReturnCallback(function ($data) use (&$result) {
            $this->json = $data;
            return $result;
        });
        $factory = $this->createStub(JsonFactory::class);
        $factory->method('create')->willReturn($result);
        return $factory;
    }
}
