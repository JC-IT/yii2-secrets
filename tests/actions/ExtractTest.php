<?php

declare(strict_types=1);

namespace JCIT\secrets\actions;

use JCIT\secrets\adapters\Yii;
use JCIT\secrets\interfaces\StorageInterface;
use JCIT\secrets\SecretOccurrence;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use yii\base\InvalidArgumentException;
use yii\console\Controller;

/**
 * @covers \JCIT\secrets\actions\Extract
 */
class ExtractTest extends TestCase
{
    private function getAction(): Extract
    {
        /** @var MockObject|Controller $controller */
        $controller = self::getMockBuilder(Controller::class)->disableOriginalConstructor()->getMock();
        $controller->expects(self::any())
            ->method('stdout')
            ->willReturn(1);

        return new Extract('extract', $controller);
    }

    public function testInvalidSourcePath(): void
    {
        $storage = $this->getMockBuilder(StorageInterface::class)->getMock();
        $yii = $this->getMockBuilder(Yii::class)->getMock();
        $action = $this->getAction();
        $action->sourcePath = __DIR__ . '/extractTestSource';

        $yii->expects(self::once())
            ->method('getAlias')
            ->with($action->sourcePath)
            ->willReturn(false);

        self::expectException(InvalidArgumentException::class);
        $action->run($yii, $storage);
    }

    public function testRun(): void
    {
        $storage = $this->getMockBuilder(StorageInterface::class)->getMock();

        $storage->expects(self::exactly(2))
            ->method('prepare')
            ->withConsecutive(
                ['secret1', [new SecretOccurrence(__DIR__ . '/extractTestSource/source.php', 7, 'defaultValue1')]],
                ['secret2', [new SecretOccurrence(__DIR__ . '/extractTestSource/source.php', 8)]],
            );

        $action = $this->getAction();
        $action->sourcePath = __DIR__ . '/extractTestSource';

        $yii = $this->getMockBuilder(Yii::class)->getMock();
        $yii->expects(self::once())
            ->method('getAlias')
            ->with($action->sourcePath)
            ->willReturn($action->sourcePath);

        $action->run($yii, $storage);
    }
}
