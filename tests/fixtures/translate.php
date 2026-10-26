<?php

declare(strict_types=1);

/**
 * Derafu: Symfony Translation Bundle - Translation resources for Symfony applications.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

use Derafu\TestsTranslationBundle\Fixture\Kernel;
use Symfony\Contracts\Translation\TranslatorInterface;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

if (!isset($argv) || count($argv) !== 4) {
    throw new InvalidArgumentException('Expected the project directory, message key and domain.');
}

$project = $argv[1];
$kernel = new Kernel($project, directories: [$project . '/packages/first', $project . '/packages/second']);
try {
    $kernel->boot();
    $translator = $kernel->getContainer()->get('test.translator');
    if (!$translator instanceof TranslatorInterface) {
        throw new LogicException('The application translator is not available.');
    }
    echo $translator->trans($argv[2], domain: $argv[3]);
} finally {
    $kernel->shutdown();
}
