<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Controller\Portal;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\Action;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\View\Result\Page;

class Index extends Action implements HttpGetActionInterface
{
    public function execute(): Page
    {
        return $this->resultFactory->create(ResultFactory::TYPE_PAGE);
    }
}
