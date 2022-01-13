<?php

declare(strict_types=1);

/** @var \JCIT\secrets\components\SecretsService $secrets */

$test1 = $secrets->get('secret1', 'defaultValue1');
$test2 = $secrets->getAndThrowOnNull('secret2');
$test3 = $secrets->get(('secret3'), 'defaultValue3');
$secret4 = 'secret4';
$test4 = $secrets->get($secret4, 'defaultValue4');
