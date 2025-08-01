<?php
declare(strict_types=1);

namespace Tirehub\TransferNetwork\Controller\Adminhtml\Relation;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Tirehub\TransferNetwork\Model\ResourceModel\LocationRelation\CollectionFactory;

class Export extends Action
{
    const ADMIN_RESOURCE = 'Tirehub_TransferNetwork::relation';

    protected $fileFactory;
    protected $collectionFactory;
    protected $filesystem;

    public function __construct(
        Context $context,
        FileFactory $fileFactory,
        CollectionFactory $collectionFactory,
        Filesystem $filesystem
    ) {
        parent::__construct($context);
        $this->fileFactory = $fileFactory;
        $this->collectionFactory = $collectionFactory;
        $this->filesystem = $filesystem;
    }

    public function execute()
    {
        try {
            $format = $this->getRequest()->getParam('format', 'excel');

            if ($format === 'excel') {
                return $this->exportToExcel();
            } else {
                return $this->exportToCsv();
            }

        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Something went wrong while exporting relations.'));
        }

        $resultRedirect = $this->resultRedirectFactory->create();
        return $resultRedirect->setPath('*/*/');
    }

    protected function exportToExcel()
    {
        if (!class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
            return $this->exportToCsv();
        }

        $collection = $this->collectionFactory->create();
        $collection->setOrder('relation_id', 'ASC');

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();
        $worksheet->setTitle('Relations');

        $headers = ['active', 'TransferTo', 'TransferFrom', 'CutoffDays', 'CutoffTime', 'UnloadMinutes'];

        $columnLetters = ['A', 'B', 'C', 'D', 'E', 'F'];
        foreach ($headers as $index => $header) {
            $worksheet->setCellValue($columnLetters[$index] . '1', $header);
        }

        $headerStyle = [
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E0E0E0']
            ]
        ];
        $worksheet->getStyle('A1:F1')->applyFromArray($headerStyle);

        $row = 2;
        foreach ($collection as $relation) {
            $worksheet->setCellValue('A' . $row, $relation->getActive() ? 'Y' : 'N');
            $worksheet->setCellValue('B' . $row, $relation->getLocationIdTo());
            $worksheet->setCellValue('C' . $row, $relation->getLocationIdFrom());
            $worksheet->setCellValue('D' . $row, $relation->getCutoffDays() ?: '');
            $worksheet->setCellValue('E' . $row, $relation->getCutoffTime() ?: '');
            $worksheet->setCellValue('F' . $row, $relation->getUnloadMinutes() ?: '');
            $row++;
        }

        foreach (range('A', 'F') as $col) {
            $worksheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

        $varDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        $fileName = 'transfer_network_relations_' . date('Y-m-d_H-i-s') . '.xlsx';
        $filePath = $varDirectory->getAbsolutePath($fileName);

        $writer->save($filePath);

        $fileContent = $varDirectory->readFile($fileName);
        $varDirectory->delete($fileName);

        return $this->fileFactory->create(
            $fileName,
            $fileContent,
            DirectoryList::VAR_DIR,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
    }

    protected function exportToCsv()
    {
        $collection = $this->collectionFactory->create();
        $collection->setOrder('relation_id', 'ASC');

        $csvData = [];
        $csvData[] = ['active', 'TransferTo', 'TransferFrom', 'CutoffDays', 'CutoffTime', 'UnloadMinutes'];

        foreach ($collection as $relation) {
            $csvData[] = [
                $relation->getActive() ? 'Y' : 'N',
                $relation->getLocationIdTo(),
                $relation->getLocationIdFrom(),
                $relation->getCutoffDays() ?: '',
                $relation->getCutoffTime() ?: '',
                $relation->getUnloadMinutes() ?: ''
            ];
        }

        $csvContent = '';
        foreach ($csvData as $row) {
            $csvContent .= '"' . implode('","', $row) . '"' . "\n";
        }

        $fileName = 'transfer_network_relations_' . date('Y-m-d_H-i-s') . '.csv';

        return $this->fileFactory->create(
            $fileName,
            $csvContent,
            DirectoryList::VAR_DIR,
            'text/csv'
        );
    }
}
