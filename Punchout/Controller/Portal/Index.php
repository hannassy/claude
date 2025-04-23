<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Controller\Portal;

use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\Action;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\View\Result\Page;
use Tirehub\Punchout\Api\DisablePunchoutModeInterface;

class Index extends Action implements HttpGetActionInterface
{
    public function __construct(
        private readonly DisablePunchoutModeInterface $disablePunchoutMode,
        Context $context
    ) {
        parent::__construct($context);
    }

    public function execute(): Page
    {
        $this->disablePunchoutMode->execute();

        return $this->resultFactory->create(ResultFactory::TYPE_PAGE);
    }
}
