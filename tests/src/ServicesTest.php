<?php

declare(strict_types=1);

/**
 * Derafu: Symfony Translation Bundle - Translation resources for Symfony applications.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsTranslationBundle;

use Derafu\Translation\Translation\TranslationResourceProvider;
use Derafu\TranslationBundle\Translation\TranslatorFactory;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Verifies the real YAML without overriding Symfony's native translator alias.
 * Tag consumption and translation behavior are tested with the real kernel.
 */
#[CoversNothing]
final class ServicesTest extends TestCase
{
    private function loadContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        (new YamlFileLoader($container, new FileLocator(dirname(__DIR__, 2) . '/resources/config')))
            ->load('symfony-translation-bundle-services.yaml');

        return $container;
    }

    public function testTheConfigurationKeepsServicesPrivateAndDeclaresItsProviderTag(): void
    {
        $container = $this->loadContainer();
        self::assertFalse($container->getDefinition(TranslatorFactory::class)->isPublic());
        self::assertFalse($container->getDefinition(TranslationResourceProvider::class)->isPublic());
        self::assertSame([TranslationResourceProvider::class => [[]]], $container->findTaggedServiceIds('derafu_translation.resource_provider'));
        self::assertFalse($container->hasAlias(TranslatorInterface::class));
    }

    public function testEveryDeclaredServiceCanBeConstructed(): void
    {
        $container = $this->loadContainer();
        foreach ($container->getDefinitions() as $definition) {
            $definition->setPublic(true);
        }
        $container->compile();
        self::assertInstanceOf(TranslatorFactory::class, $container->get(TranslatorFactory::class));
        $provider = $container->get(TranslationResourceProvider::class);
        self::assertInstanceOf(TranslationResourceProvider::class, $provider);
        self::assertNotEmpty(iterator_to_array((static function () use ($provider): iterable {
            yield from $provider->getDirectories();
        })()));
    }
}
