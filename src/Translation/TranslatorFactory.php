<?php

declare(strict_types=1);

/**
 * Derafu: Symfony Translation Bundle - Translation resources for Symfony applications.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TranslationBundle\Translation;

use Derafu\Translation\Contract\TranslationResourceProviderInterface;
use Derafu\Translation\TranslationResourceRegistrar;
use Psr\Container\ContainerInterface;
use Symfony\Bundle\FrameworkBundle\Translation\Translator;
use Symfony\Component\Translation\Formatter\MessageFormatterInterface;

/**
 * Adds provider files before Symfony's own files at translator construction.
 *
 * Using the native constructor's resource list preserves application priority,
 * discovered locales, catalogue freshness checks and normal cache warming.
 *
 * @internal
 */
final class TranslatorFactory
{
    /**
     * @param array<string, list<string>> $loaderIds Symfony loader aliases.
     * @param array{cache_dir?: ?string, debug?: bool, resource_files?: array<string, list<mixed>>, scanned_directories?: list<string>, cache_vary?: array<array-key, mixed>} $options Symfony translator options.
     * @param list<string> $enabledLocales Application locale restrictions.
     * @param iterable<TranslationResourceProviderInterface> $providers Tagged providers in registration order.
     */
    public function create(
        ContainerInterface $container,
        MessageFormatterInterface $formatter,
        string $defaultLocale,
        array $loaderIds,
        array $options,
        array $enabledLocales,
        iterable $providers,
    ): Translator {
        $collector = new TranslationResourceCollector($defaultLocale);
        $registrar = new TranslationResourceRegistrar($collector);
        $directories = [];

        foreach ($providers as $provider) {
            foreach ($provider->getDirectories() as $directory) {
                $registrar->registerDirectory($directory);
                $directories[] = $directory;
            }
        }

        $files = $collector->getResourceFiles();
        foreach ($options['resource_files'] ?? [] as $locale => $applicationFiles) {
            $files[$locale] = array_merge($files[$locale] ?? [], $applicationFiles);
        }
        $options['resource_files'] = $files;
        $options['scanned_directories'] = array_values(array_unique(array_merge(
            $directories,
            $options['scanned_directories'] ?? [],
        )));

        return new Translator($container, $formatter, $defaultLocale, $loaderIds, $options, $enabledLocales);
    }
}
