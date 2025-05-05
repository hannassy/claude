<?php
declare(strict_types=1);

namespace Tirehub\Punchout\Service;

use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\Logger\Monolog;

class TokenGenerator
{
    public function __construct(
        private readonly EncryptorInterface $encryptor,
        private readonly UrlInterface $urlBuilder,
        private readonly Monolog $logger
    ) {
    }

    /**
     * Generate an encrypted token containing the buyer cookie
     *
     * @param string $buyerCookie
     * @return string
     */
    public function generateToken(string $buyerCookie): string
    {
        try {
            $tokenData = [
                'cookie' => $buyerCookie,
                'timestamp' => time(),
                // Add a random nonce to prevent token reuse
                'nonce' => bin2hex(random_bytes(8))
            ];

            $jsonData = json_encode($tokenData);
            $encrypted = $this->encryptor->encrypt($jsonData);

            // Base64 encode to make it URL-safe
            return base64_encode($encrypted);
        } catch (\Exception $e) {
            $this->logger->error('Punchout: Error generating token: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Generate a secure URL for the portal page
     *
     * @param string $buyerCookie
     * @return string
     */
    public function generatePortalUrl(string $buyerCookie): string
    {
        $token = $this->generateToken($buyerCookie);
        return $this->urlBuilder->getUrl('punchout/portal', ['token' => $token]);
    }

    /**
     * Generate a secure URL for the shopping start page
     *
     * @param string $buyerCookie
     * @return string
     */
    public function generateShoppingStartUrl(string $buyerCookie): string
    {
        $token = $this->generateToken($buyerCookie);
        return $this->urlBuilder->getUrl('punchout/shopping/start', ['token' => $token]);
    }
}
