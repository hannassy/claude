<?php
declare(strict_types=1);

namespace Tirehub\TransferNetwork\Controller\Adminhtml\Relation;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\File\Csv;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Tirehub\TransferNetwork\Model\LocationRelationFactory;
use Tirehub\TransferNetwork\Model\ResourceModel\LocationRelation as LocationRelationResource;
use Tirehub\TransferNetwork\Model\ResourceModel\Location\CollectionFactory as LocationCollectionFactory;

class Import extends Action
{
    const ADMIN_RESOURCE = 'Tirehub_TransferNetwork::relation';

    protected $csv;
    protected $filesystem;
    protected $relationFactory;
    protected $relationResource;
    protected $locationCollectionFactory;

    public function __construct(
        Context $context,
        Csv $csv,
        Filesystem $filesystem,
        LocationRelationFactory $relationFactory,
        LocationRelationResource $relationResource,
        LocationCollectionFactory $locationCollectionFactory
    ) {
        parent::__construct($context);
        $this->csv = $csv;
        $this->filesystem = $filesystem;
        $this->relationFactory = $relationFactory;
        $this->relationResource = $relationResource;
        $this->locationCollectionFactory = $locationCollectionFactory;
    }

    public function execute()
    {
        $jsonResult = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        if ($this->getRequest()->isPost()) {
            try {
                $importFile = $this->getRequest()->getParam('import_file');

                if (empty($importFile) || !is_array($importFile) || empty($importFile[0]['file'])) {
                    throw new \Exception('No file uploaded.');
                }

                $fileName = $importFile[0]['file'];
                $mediaDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
                $filePath = $mediaDirectory->getAbsolutePath('import/') . $fileName;

                if (!$mediaDirectory->isFile('import/' . $fileName)) {
                    throw new \Exception('Uploaded file not found.');
                }

                $extension = pathinfo($fileName, PATHINFO_EXTENSION);

                if ($extension === 'csv') {
                    $this->processCsvFile($filePath);
                } else {
                    $this->processExcelFile($filePath);
                }

                $mediaDirectory->delete('import/' . $fileName);

                $jsonResult->setData([
                    'success' => true,
                    'message' => __('Relations imported successfully.')
                ]);

            } catch (\Exception $e) {
                $jsonResult->setData([
                    'success' => false,
                    'message' => $e->getMessage()
                ]);
            }
        } else {
            $jsonResult->setData([
                'success' => false,
                'message' => __('Invalid request method.')
            ]);
        }

        return $jsonResult;
    }

    protected function processCsvFile(string $filePath): void
    {
        $csvData = $this->csv->getData($filePath);
        $this->processImportData($csvData);
    }

    protected function processExcelFile(string $filePath): void
    {
        if (!class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
            throw new \Exception('PhpSpreadsheet library is not available. Please install it via Composer.');
        }

        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($filePath);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();

        $csvData = [];
        foreach ($worksheet->getRowIterator() as $row) {
            $rowData = [];
            foreach ($row->getCellIterator() as $cell) {
                $value = $cell->getValue();
                if ($value instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) {
                    $value = $value->getPlainText();
                }
                $rowData[] = $value;
            }
            $csvData[] = $rowData;
        }

        $this->processImportData($csvData);
    }

    protected function processImportData(array $csvData): void
    {
        if (empty($csvData)) {
            throw new \Exception('No data found in file.');
        }

        $headers = array_map(function($value) {
            return $value !== null ? trim($value) : '';
        }, array_shift($csvData));

        $expectedHeaders = ['active', 'TransferTo', 'TransferFrom', 'CutoffDays', 'CutoffTime', 'UnloadMinutes'];

        foreach ($expectedHeaders as $header) {
            if (!in_array($header, $headers)) {
                throw new \Exception("Missing required column: {$header}");
            }
        }

        $locationIds = $this->getValidLocationIds();
        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        foreach ($csvData as $rowIndex => $row) {
            $rowNumber = $rowIndex + 2;

            if (count($row) < count($expectedHeaders)) {
                $skipped++;
                $errors[] = "Row {$rowNumber}: Not enough columns";
                continue;
            }

            $data = array_combine($headers, array_map(function($value) {
                return $value !== null ? trim((string)$value) : '';
            }, $row));

            try {
                if (empty($data['TransferTo']) || empty($data['TransferFrom'])) {
                    $skipped++;
                    $errors[] = "Row {$rowNumber}: TransferTo and TransferFrom are required";
                    continue;
                }

                $transferTo = (int)$data['TransferTo'];
                $transferFrom = (int)$data['TransferFrom'];

                if (!in_array($transferTo, $locationIds)) {
                    $skipped++;
                    $errors[] = "Row {$rowNumber}: TransferTo location {$transferTo} does not exist or is inactive";
                    continue;
                }

                if (!in_array($transferFrom, $locationIds)) {
                    $skipped++;
                    $errors[] = "Row {$rowNumber}: TransferFrom location {$transferFrom} does not exist or is inactive";
                    continue;
                }

                $existingRelation = $this->relationFactory->create()->getCollection()
                    ->addFieldToFilter('location_id_from', $transferFrom)
                    ->addFieldToFilter('location_id_to', $transferTo)
                    ->getFirstItem();

                $isUpdate = false;
                if ($existingRelation->getId()) {
                    $relation = $existingRelation;
                    $isUpdate = true;
                } else {
                    $relation = $this->relationFactory->create();
                }

                $relation->setLocationIdFrom($transferFrom);
                $relation->setLocationIdTo($transferTo);
                $relation->setActive(strtoupper($data['active']) === 'Y');

                if (!empty($data['CutoffDays']) && is_numeric($data['CutoffDays'])) {
                    $relation->setCutoffDays((float)$data['CutoffDays']);
                } else {
                    $relation->setCutoffDays(null);
                }

                if (!empty($data['CutoffTime'])) {
                    $cutoffTime = $this->validateAndFormatTime($data['CutoffTime']);
                    if ($cutoffTime) {
                        $relation->setCutoffTime($cutoffTime);
                    } else {
                        $errors[] = "Row {$rowNumber}: Invalid time format for CutoffTime: {$data['CutoffTime']}";
                    }
                } else {
                    $relation->setCutoffTime(null);
                }

                if (!empty($data['UnloadMinutes']) && is_numeric($data['UnloadMinutes'])) {
                    $relation->setUnloadMinutes((int)$data['UnloadMinutes']);
                } else {
                    $relation->setUnloadMinutes(null);
                }

                $this->relationResource->save($relation);

                if ($isUpdate) {
                    $updated++;
                } else {
                    $imported++;
                }

            } catch (\Exception $e) {
                $skipped++;
                $errors[] = "Row {$rowNumber}: {$e->getMessage()}";
                continue;
            }
        }

        if ($imported === 0 && $updated === 0 && $skipped > 0) {
            throw new \Exception("No valid relations were processed. All {$skipped} rows were skipped.");
        }

        $message = "Import completed: {$imported} new relations imported, {$updated} relations updated";

        if ($skipped > 0) {
            $message .= ", {$skipped} rows skipped";
        }

        if (!empty($errors)) {
            $errorMessage = "Errors encountered:\n" . implode("\n", array_slice($errors, 0, 5));
            if (count($errors) > 5) {
                $errorMessage .= "\n... and " . (count($errors) - 5) . " more errors";
            }
            throw new \Exception($message . "\n\n" . $errorMessage);
        }
    }

    protected function validateAndFormatTime(string $timeString): ?string
    {
        $timeString = trim($timeString);

        if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $timeString, $matches)) {
            $hours = (int)$matches[1];
            $minutes = (int)$matches[2];
            $seconds = isset($matches[3]) ? (int)$matches[3] : 0;

            if ($hours >= 0 && $hours <= 23 && $minutes >= 0 && $minutes <= 59 && $seconds >= 0 && $seconds <= 59) {
                return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
            }
        }

        try {
            $date = new \DateTime($timeString);
            return $date->format('H:i:s');
        } catch (\Exception $e) {
            return null;
        }
    }

    protected function getValidLocationIds(): array
    {
        $collection = $this->locationCollectionFactory->create();
        $collection->addFieldToFilter('active', 1);

        $locationIds = [];
        foreach ($collection as $location) {
            $locationIds[] = $location->getLocationId();
        }

        return $locationIds;
    }
}
