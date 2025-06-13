<?php
declare(strict_types=1);

namespace Tirehub\TirePromo\Model;

use Tirehub\TirePromo\Api\LogRepositoryInterface;
use Tirehub\TirePromo\Api\Data\LogInterface;
use Tirehub\TirePromo\Model\ResourceModel\Log as ResourceModel;
use Tirehub\TirePromo\Model\LogFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\CouldNotDeleteException;

class LogRepository implements LogRepositoryInterface
{
    public function __construct(
        private readonly ResourceModel $resource,
        private readonly LogFactory $logFactory
    ) {
    }

    public function save(LogInterface $log): LogInterface
    {
        try {
            $this->resource->save($log);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(
                __('Could not save the promo code log: %1', $exception->getMessage())
            );
        }
        return $log;
    }

    public function getById(int $entityId): LogInterface
    {
        $log = $this->logFactory->create();
        $this->resource->load($log, $entityId);

        if (!$log->getId()) {
            throw new NoSuchEntityException(__('Promo code log with id "%1" does not exist.', $entityId));
        }

        return $log;
    }

    public function delete(LogInterface $log): bool
    {
        try {
            $this->resource->delete($log);
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(
                __('Could not delete the promo code log: %1', $exception->getMessage())
            );
        }
        return true;
    }

    public function deleteById(int $entityId): bool
    {
        return $this->delete($this->getById($entityId));
    }
}
