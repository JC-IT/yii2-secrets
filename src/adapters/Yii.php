<?php

declare(strict_types=1);

namespace JCIT\secrets\adapters;

/**
 * @codeCoverageIgnore just a wrapper for testing purposes
 */
class Yii
{
    public function getAlias(string $alias, bool $throwException = true): string|false
    {
        return \Yii::getAlias($alias, $throwException);
    }
}
