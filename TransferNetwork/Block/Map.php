<?php
declare(strict_types=1);

namespace Tirehub\TransferNetwork\Block;

use Magento\Framework\View\Element\Template;
use Tirehub\TransferNetwork\Service\GetMapData;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

class Map extends Template
{
    public function __construct(
        Template\Context $context,
        private readonly GetMapData $getMapData,
        private readonly SerializerInterface $serializer,
        private readonly LoggerInterface $logger,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getItems(): string
    {
        try {
            $result = $this->getMapData->execute();
            return $this->serializer->serialize($result);
        } catch (LocalizedException $e) {
            $this->logger->error('Error loading map data: ' . $e->getMessage());
            return $this->serializer->serialize([]);
        }
    }
}
