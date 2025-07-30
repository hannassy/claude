<?php
declare(strict_types=1);

namespace Tirehub\TransferNetwork\Controller\Adminhtml\Relation;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\MediaStorage\Model\File\UploaderFactory;

class UploadFile extends Action
{
    const ADMIN_RESOURCE = 'Tirehub_TransferNetwork::relation';

    protected $uploaderFactory;
    protected $filesystem;

    public function __construct(
        Context $context,
        UploaderFactory $uploaderFactory,
        Filesystem $filesystem
    ) {
        parent::__construct($context);
        $this->uploaderFactory = $uploaderFactory;
        $this->filesystem = $filesystem;
    }

    public function execute()
    {
        $jsonResult = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        try {
            $uploader = $this->uploaderFactory->create(['fileId' => 'import_file']);
            $uploader->setAllowedExtensions(['csv', 'xlsx', 'xls']);
            $uploader->setAllowRenameFiles(true);
            $uploader->setFilesDispersion(false);

            $mediaDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
            $result = $uploader->save($mediaDirectory->getAbsolutePath('import/'));

            if (!$result) {
                throw new \Exception('File upload failed.');
            }

            $jsonResult->setData([
                'name' => $result['name'],
                'file' => $result['file'],
                'size' => $result['size'],
                'url' => '',
                'type' => $result['type'] ?? ''
            ]);

        } catch (\Exception $e) {
            $jsonResult->setData([
                'error' => $e->getMessage(),
                'errorcode' => $e->getCode()
            ]);
        }

        return $jsonResult;
    }
}
