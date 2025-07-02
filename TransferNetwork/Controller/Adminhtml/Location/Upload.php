<?php
declare(strict_types=1);

namespace Tirehub\TransferNetwork\Controller\Adminhtml\Location;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\MediaStorage\Model\File\UploaderFactory;
use Magento\Framework\Filesystem;
use Magento\Framework\App\Filesystem\DirectoryList;
use Tirehub\TransferNetwork\Model\LocationFactory;
use Tirehub\TransferNetwork\Model\ResourceModel\Location as LocationResource;

class Upload extends Action
{
    const ADMIN_RESOURCE = 'Tirehub_TransferNetwork::location';

    protected $resultJsonFactory;
    protected $dataPersistor;
    protected $uploaderFactory;
    protected $filesystem;
    protected $locationFactory;
    protected $locationResource;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        DataPersistorInterface $dataPersistor,
        UploaderFactory $uploaderFactory,
        Filesystem $filesystem,
        LocationFactory $locationFactory,
        LocationResource $locationResource
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->dataPersistor = $dataPersistor;
        $this->uploaderFactory = $uploaderFactory;
        $this->filesystem = $filesystem;
        $this->locationFactory = $locationFactory;
        $this->locationResource = $locationResource;
    }

    public function execute()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->resultRedirectFactory->create()->setPath('*/*/uploadPage');
        }

        $resultRedirect = $this->resultRedirectFactory->create();

        try {
            $uploader = $this->uploaderFactory->create(['fileId' => 'excel_file']);
            $uploader->setAllowedExtensions(['xlsx', 'xls']);
            $uploader->setAllowRenameFiles(true);
            $uploader->setFilesDispersion(true);

            $mediaDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
            $result = $uploader->save($mediaDirectory->getAbsolutePath('transfer_network/uploads/'));

            if (!$result) {
                throw new LocalizedException(__('File upload failed.'));
            }

            $filePath = $mediaDirectory->getAbsolutePath('transfer_network/uploads/' . $result['file']);

            // Process Excel file
            $importedCount = $this->processExcelFile($filePath);

            // Clean up uploaded file
            if (file_exists($filePath)) {
                unlink($filePath);
            }

            return $resultRedirect->setPath('*/*/uploadPage', [
                'success' => 1,
                'count' => $importedCount
            ]);

        } catch (\Exception $e) {
            return $resultRedirect->setPath('*/*/uploadPage', [
                'error' => $e->getMessage()
            ]);
        }
    }

    private function processExcelFile(string $filePath): int
    {
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();

        $importedCount = 0;
        $highestRow = $worksheet->getHighestRow();

        // Skip header row, start from row 2
        for ($row = 2; $row <= $highestRow; $row++) {
            $locationId = $worksheet->getCell('A' . $row)->getValue();
            $locationName = $worksheet->getCell('B' . $row)->getValue();
            $latitude = $worksheet->getCell('C' . $row)->getValue();
            $longitude = $worksheet->getCell('D' . $row)->getValue();
            $rdcCluster = $worksheet->getCell('E' . $row)->getValue();
            $pinColor = $worksheet->getCell('F' . $row)->getValue();
            $active = $worksheet->getCell('G' . $row)->getValue();
            $rdcInventoryVisible = $worksheet->getCell('H' . $row)->getValue();

            // Skip empty rows
            if (empty($locationId) || empty($locationName)) {
                continue;
            }

            // Convert latitude/longitude from comma to dot decimal
            $latitude = str_replace(',', '.', (string)$latitude);
            $longitude = str_replace(',', '.', (string)$longitude);

            // Convert YES/NO to 1/0 with null safety
            $active = $this->convertYesNoToBool($active);
            $rdcInventoryVisible = $this->convertYesNoToBool($rdcInventoryVisible);

            // Clean up string values
            $rdcCluster = $rdcCluster ? (string)$rdcCluster : '';
            $pinColor = $pinColor ? (string)$pinColor : '';

            // Create or update location
            $location = $this->locationFactory->create();
            $this->locationResource->load($location, $locationId);

            $location->setData([
                'location_id' => $locationId,
                'location_name' => (string)$locationName,
                'latitude' => (float)$latitude,
                'longitude' => (float)$longitude,
                'rdc_cluster' => $rdcCluster,
                'pin_color' => $pinColor,
                'active' => $active,
                'rdc_inventory_visible' => $rdcInventoryVisible
            ]);

            $this->locationResource->save($location);
            $importedCount++;
        }

        return $importedCount;
    }

    private function convertYesNoToBool($value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        $stringValue = strtoupper((string)$value);
        return ($stringValue === 'YES' || $stringValue === '1' || $stringValue === 'TRUE') ? 1 : 0;
    }
}