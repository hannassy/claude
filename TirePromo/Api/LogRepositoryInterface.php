<?php
declare(strict_types=1);

namespace Tirehub\TirePromo\Api;

use Tirehub\TirePromo\Api\Data\LogInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

interface LogRepositoryInterface
{
    public function save(LogInterface $log): LogInterface;

    public function getById(int $entityId): LogInterface;

    public function delete(LogInterface $log): bool;

    public function deleteById(int $entityId): bool;
}
