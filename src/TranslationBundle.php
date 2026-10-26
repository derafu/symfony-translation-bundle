<?php

declare(strict_types=1);

/**
 * Derafu: Symfony Translation Bundle - Translation resources for Symfony applications.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TranslationBundle;

use Derafu\TranslationBundle\DependencyInjection\Compiler\TranslationResourcesPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * Makes tagged Derafu resource providers available to Symfony's translator.
 */
final class TranslationBundle extends AbstractBundle
{
    protected string $extensionAlias = 'derafu_translation';

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new TranslationResourcesPass());
    }

    /** @param array<string, mixed> $config */
    public function loadExtension(array $config, ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        $configurator->import('../resources/config/symfony-translation-bundle-services.yaml');
    }
}
