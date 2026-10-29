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

use Composer\InstalledVersions;
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

    /**
     * {@inheritDoc}
     */
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new TranslationResourcesPass());
    }

    /**
     * {@inheritDoc}
     *
     * @param array<string, mixed> $config Validated configuration supplied by Symfony.
     */
    public function loadExtension(array $config, ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        // Resolve the required package through Composer: FrameworkBundle's
        // extension loader has no search paths for sibling checkout imports.
        $configurator->import(InstalledVersions::getInstallPath('derafu/translation') . '/resources/config/translation-resources-services.yaml');
        $configurator->import('../resources/config/symfony-translation-bundle-services.yaml');
    }
}
