<?php
declare(strict_types=1);

namespace Tirehub\TransferNetwork\Controller\Adminhtml\Location;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\UrlInterface;
use Magento\Theme\Model\Design\Config\FileUploader\FileProcessor;
use Magento\Framework\Controller\Result\Json as JsonResult;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Filesystem;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\MediaStorage\Model\File\UploaderFactory;
use Magento\Framework\App\RequestInterface;

class UploadIcon extends Action
{
    private const IMAGE_PATH = 'transfernetwork/images/';

    public function __construct(
        Context $context,
        private readonly JsonFactory $resultJsonFactory,
        private readonly UploaderFactory $uploaderFactory,
        private readonly Filesystem $filesystem
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        /** @var JsonResult $result */
        $result = $this->resultJsonFactory->create();
        $icon = $this->getRequest()->getFiles('icon');

        try {
            if (isset($icon['name']) && $icon['name'] !== '') {
                $uploader = $this->uploaderFactory->create(['fileId' => 'icon']);
                $uploader->setAllowedExtensions(['jpg', 'jpeg', 'png']);
                $uploader->setAllowRenameFiles(true);

                $mediaDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
                // Customize your folder name below
                $target = $mediaDirectory->getAbsolutePath(self::IMAGE_PATH);

                // Save file to target folder
                $resultData = $uploader->save($target);

                if ($resultData && isset($resultData['file'])) {
                    // Build the URL to be returned in JSON
                    $imageUrl = self::IMAGE_PATH . $resultData['file'];

                    return $result->setData([
                        'name' => $resultData['name'] ?? '',
                        'type' => $resultData['type'] ?? '',
                        'size' => $resultData['size'] ?? 0,
                        'url'  => $this->_url->getBaseUrl(
                            ['_type' => UrlInterface::URL_TYPE_MEDIA]
                        ) . $imageUrl
                    ]);
                }
            }

            // If no file was actually uploaded, or some other condition
            return $result->setData([
                'error' => __('No file uploaded.')
            ]);
        } catch (\Exception $e) {
            return $result->setData([
                'error' => $e->getMessage(),
                'errorcode' => $e->getCode()
            ]);
        }
    }
}
