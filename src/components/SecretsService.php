<?php
declare(strict_types=1);

namespace JCIT\secrets\components;

use JCIT\secrets\exceptions\SecretsException;
use JCIT\secrets\interfaces\SecretsInterface;
use JCIT\secrets\interfaces\StorageInterface;

class SecretsService implements SecretsInterface
{
    public function __construct(
        private StorageInterface $storage
    ) {
    }

    public function get(string $secret, string|int|null $default = null): string|int|null
    {
        return $this->storage->get($secret) ?? $default;
    }

    public function getAndThrowOnEmpty(string $secret): string|int
    {
        $result = $this->get($secret);

        if (is_null($result)) {
            if ($this->isExtractCall()) {
                return 'EXTRACT CALL';
            }

            throw SecretsException::notFound($secret);
        }

        return $result;
    }

    private function isExtractCall(): bool
    {
        return false;
    }
}
