<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Model\Client;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultInterface;

interface ClientInterface
{
    /**
     * Process item request for punchout
     *
     * @param RequestInterface $request
     * @return ResultInterface
     */
    public function processItem(RequestInterface $request): ResultInterface;

    /**
     * Process setup request for punchout
     *
     * @param RequestInterface $request
     * @return ResultInterface
     */
    public function processRequest(RequestInterface $request): ResultInterface;

    /**
     * Process shopping start for punchout
     *
     * @param RequestInterface $request
     * @return ResultInterface
     */
    public function processShoppingStart(RequestInterface $request): ResultInterface;

    /**
     * Process portal address submission
     *
     * @param RequestInterface $request
     * @return ResultInterface
     */
    public function processPortalAddressSubmit(RequestInterface $request): ResultInterface;
}
