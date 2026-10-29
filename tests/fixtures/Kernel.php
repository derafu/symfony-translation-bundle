<?php

declare(strict_types=1);

/**
 * Derafu: Symfony Translation Bundle - Translation resources for Symfony applications.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsTranslationBundle\Fixture;

use Derafu\TranslationBundle\TranslationBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

/**
 * A real application with constructor-configured providers and native resources.
 */
final class Kernel extends BaseKernel
{
    /**
     * @param array<string, mixed> $framework Configuration supplied by the application.
     * @param list<string> $directories Provider directories in registration order.
     */
    public function __construct(
        private readonly string $projectDirectory,
        private readonly array $framework = [],
        private readonly array $directories = [],
        private readonly bool $withLogger = false,
        bool $debug = true,
    ) {
        parent::__construct('test', $debug);
    }

    /** @return iterable<BundleInterface> */
    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new TranslationBundle();
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(function (ContainerBuilder $container): void {
            $container->loadFromExtension('framework', array_replace([
                'secret' => 'translation-bundle-test-secret',
                'default_locale' => 'es',
                'translator' => ['fallbacks' => ['en']],
            ], $this->framework));
            $container->loadFromExtension('derafu_translation', []);
            foreach ($this->directories as $index => $directory) {
                $container->setParameter('fixture.provider.' . $index . '.directories', [$directory]);
            }
        });
        // Each scenario contribution is declared in YAML. PHP supplies only
        // its directory value and chooses the fixtures needed by the scenario.
        foreach (array_keys($this->directories) as $index) {
            $loader->load(__DIR__ . '/config/provider-' . $index . '.yaml');
        }
        if ($this->withLogger) {
            $loader->load(__DIR__ . '/config/logger.yaml');
        }
        if (($this->framework['translator'] ?? true) !== false) {
            $loader->load(__DIR__ . '/config/translator-access.yaml');
        }
    }

    public function getProjectDir(): string
    {
        return $this->projectDirectory;
    }

    public function getCacheDir(): string
    {
        return $this->projectDirectory . '/var/cache';
    }

    public function getLogDir(): string
    {
        return $this->projectDirectory . '/var/log';
    }
}
