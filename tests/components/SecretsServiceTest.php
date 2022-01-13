<?php

declare(strict_types=1);

namespace JCIT\secrets\tests\components;

use JCIT\secrets\components\SecretsService;
use JCIT\secrets\exceptions\SecretsException;
use JCIT\secrets\interfaces\StorageInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \JCIT\secrets\components\SecretsService
 */
class SecretsServiceTest extends TestCase
{
    /**
     * @var array<string, mixed>
     */
    private array $serverBackup;
    private MockObject $storage;

    public function dataProviderGet(): array
    {
        return [
            'Value' => [
                'testValue',
                false,
            ],
            'Null value' => [
                null,
                true,
            ],
        ];
    }

    public function dataProviderGetAndThrow(): array
    {
        return [
            'Value' => [
                'testValue',
                false,
                false,
            ],
            'Null value' => [
                null,
                true,
                false,
            ],
            'Null value on extract call' => [
                SecretsService::EXTRACT_CALL_VALUE,
                false,
                true,
            ],
        ];
    }

    private function getSecrets(): SecretsService
    {
        $this->storage = $this->getMockBuilder(StorageInterface::class)->getMock();
        return new SecretsService($this->storage);
    }

    protected function setUp(): void
    {
        $this->serverBackup = $_SERVER;

        parent::setUp();
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;

        parent::tearDown();
    }

    /**
     * @dataProvider dataProviderGet
     */
    public function testGet(string|int|bool|null $value, bool $willReturnDefault): void
    {
        $secrets = $this->getSecrets();
        $secret = 'testSecret';
        $default = 'defaultValue';

        $this->storage->expects(self::once())
            ->method('get')
            ->with($secret)
            ->willReturn($value);

        self::assertEquals(!$willReturnDefault ? $value : $default, $secrets->get($secret, $default));
    }

    /**
     * @dataProvider dataProviderGetAndThrow
     */
    public function testGetAndThrow(string|int|bool|null $value, bool $willThrow, bool $isExtractCall): void
    {
        $secrets = $this->getSecrets();
        $secret = 'testSecret';

        $this->storage->expects(self::once())
            ->method('get')
            ->with($secret)
            ->willReturn($isExtractCall ? null : $value);

        if ($isExtractCall) {
            $_SERVER['argv'] = [
                'yii',
                'secrets/extract',
            ];
        }

        if ($willThrow) {
            self::expectException(SecretsException::class);
        }

        $result = $secrets->getAndThrowOnNull($secret);

        if (!$willThrow) {
            self::assertEquals($value, $result);
        }
    }
}
