<?php

declare(strict_types=1);

/**
 * Derafu: Symfony Translation Bundle - Translation resources for Symfony applications.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TranslationBundle\DependencyInjection\Compiler;

use Derafu\TranslationBundle\Translation\TranslatorFactory;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Retains Symfony's translator definition, loaders, aliases and decorators.
 */
final class TranslationResourcesPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        // FrameworkBundle removes this definition when translation is disabled.
        if (!$container->hasDefinition('translator.default')) {
            return;
        }

        $providers = [];
        foreach ($container->findTaggedServiceIds('derafu_translation.resource_provider') as $id => $tags) {
            $providers[] = new Reference($id);
        }

        $container->getDefinition('translator.default')
            ->setFactory([new Reference(TranslatorFactory::class), 'create'])
            ->setArgument(6, new IteratorArgument($providers));
    }
}
