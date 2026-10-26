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

use Symfony\Component\Translation\Translator;

/**
 * Adapts the registrar's addResource() calls to FrameworkBundle's file list.
 *
 * Discovery, filename validation and supported formats remain the responsibility
 * of Derafu's TranslationResourceRegistrar. This adapter never loads messages.
 *
 * @internal
 */
final class TranslationResourceCollector extends Translator
{
    /** @var array<string, list<mixed>> */
    private array $files = [];

    public function addResource(string $format, mixed $resource, string $locale, ?string $domain = null): void
    {
        $this->files[$locale][] = $resource;
    }

    /** @return array<string, list<mixed>> */
    public function getResourceFiles(): array
    {
        return $this->files;
    }
}
