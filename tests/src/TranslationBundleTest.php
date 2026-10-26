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

use Derafu\TestsTranslationBundle\Fixture\Kernel;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException;
use Derafu\TranslationBundle\DependencyInjection\Compiler\TranslationResourcesPass;
use Derafu\TranslationBundle\Translation\TranslationResourceCollector;
use Derafu\TranslationBundle\Translation\TranslatorFactory;
use Derafu\TranslationBundle\TranslationBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Translation\Translator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Symfony\Component\Translation\LoggingTranslator;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Tests the integration using actual providers, catalogues and compiled kernels.
 */
#[CoversClass(TranslationBundle::class)]
#[CoversClass(TranslationResourcesPass::class)]
#[CoversClass(TranslatorFactory::class)]
#[CoversClass(TranslationResourceCollector::class)]
final class TranslationBundleTest extends TestCase
{
    private string $project;

    private ?Kernel $kernel = null;

    /** @var array<array-key, mixed> */
    private array $environment;

    /** @var array<array-key, mixed> */
    private array $server;

    private string|false $shellVerbosity;

    protected function setUp(): void
    {
        $this->environment = $_ENV;
        $this->server = $_SERVER;
        $this->shellVerbosity = getenv('SHELL_VERBOSITY');
        $this->project = sys_get_temp_dir() . '/derafu-translation-bundle-' . bin2hex(random_bytes(8));
        $filesystem = new Filesystem();
        $fixtures = dirname(__DIR__) . '/fixtures/translations';
        $filesystem->mirror($fixtures . '/application', $this->project . '/translations');
        $filesystem->mirror($fixtures . '/first', $this->project . '/packages/first');
        $filesystem->mirror($fixtures . '/second', $this->project . '/packages/second');
    }

    protected function tearDown(): void
    {
        try {
            $this->kernel?->shutdown();
            (new Filesystem())->remove($this->project);
        } finally {
            $_ENV = $this->environment;
            $_SERVER = $this->server;
            putenv($this->shellVerbosity === false ? 'SHELL_VERBOSITY' : 'SHELL_VERBOSITY=' . $this->shellVerbosity);
        }
    }

    /**
     * @param array<string, mixed> $framework
     * @param list<string>|null $directories
     */
    private function boot(array $framework = [], ?array $directories = null, bool $withLogger = false, bool $debug = true): Kernel
    {
        $this->kernel = new Kernel(
            $this->project,
            $framework,
            $directories ?? [$this->project . '/packages/first', $this->project . '/packages/second'],
            $withLogger,
            $debug,
        );
        $this->kernel->boot();

        return $this->kernel;
    }

    private function translator(): TranslatorInterface
    {
        self::assertNotNull($this->kernel);
        $translator = $this->kernel->getContainer()->get('test.translator');
        self::assertInstanceOf(TranslatorInterface::class, $translator);

        return $translator;
    }

    public function testTheBundleHasItsOwnPathAndConfigurationAlias(): void
    {
        $bundle = new TranslationBundle();
        self::assertSame(dirname(__DIR__, 2), $bundle->getPath());
        $extension = $bundle->getContainerExtension();
        self::assertNotNull($extension);
        self::assertSame('derafu_translation', $extension->getAlias());
    }

    public function testTheApplicationKeepsItsNativeTranslatorAndContractAlias(): void
    {
        $kernel = $this->boot();
        $translator = $this->translator();
        self::assertInstanceOf(Translator::class, $translator);
        self::assertSame($translator, $kernel->getContainer()->get('translator'));
        self::assertSame($translator, $kernel->getContainer()->get('test.native_translator'));
        self::assertSame('es', $translator->getLocale());
        self::assertSame(['en'], $translator->getFallbackLocales());
    }

    public function testApplicationMessagesOverridePackagesAndOtherMessagesAreRetained(): void
    {
        $this->boot();
        $translator = $this->translator();
        self::assertSame('application', $translator->trans('shared'));
        self::assertSame('application only', $translator->trans('application_only'));
        self::assertSame('application Ada', $translator->trans('greeting', ['name' => 'Ada']));
        self::assertSame('first only', $translator->trans('first_only'));
        self::assertSame('second package', $translator->trans('provider_order'));
    }

    public function testTheLastRegisteredProviderWinsWithoutApplicationOverrides(): void
    {
        (new Filesystem())->remove($this->project . '/translations');
        $this->boot();
        self::assertSame('second package', $this->translator()->trans('shared'));
        self::assertSame('second package Ada', $this->translator()->trans('greeting', ['name' => 'Ada']));
    }

    public function testChangingProviderOrderChangesOnlyPackagePrecedence(): void
    {
        $this->boot(directories: [$this->project . '/packages/second', $this->project . '/packages/first']);
        self::assertSame('first package', $this->translator()->trans('provider_order'));
        self::assertSame('first package Ada', $this->translator()->trans('provider_greeting', ['name' => 'Ada']));
        self::assertSame('application', $this->translator()->trans('shared'));
    }

    public function testFallbacksAndProviderOnlyLocalesUseTheNativeCatalogues(): void
    {
        $this->boot();
        $translator = $this->translator();
        self::assertSame('English fallback Ada', $translator->trans('fallback_only', ['name' => 'Ada']));
        self::assertSame('French package Ada', $translator->trans('provider_locale', ['name' => 'Ada'], locale: 'fr'));
        self::assertSame('Items: 12', $translator->trans('Count: {count}', ['count' => 12], 'errors'));
    }

    public function testTraditionalCataloguesStillSupportParametersAndApplicationOverrides(): void
    {
        $this->boot();
        $translator = $this->translator();
        self::assertSame('Hello Ada', $translator->trans('welcome', ['%name%' => 'Ada'], 'legacy'));
        self::assertSame('legacy application', $translator->trans('shared', domain: 'legacy'));
    }

    public function testTheBundleRegistersItsOwnDerafuExceptionTranslations(): void
    {
        $this->boot(directories: []);
        $exception = new TranslatableInvalidArgumentException([
            'Translation directory "{directory}" does not exist.',
            'directory' => '/missing',
        ]);
        self::assertSame('El directorio de traducciones "/missing" no existe.', $exception->trans($this->translator()));
    }

    public function testTranslationDisabledDoesNotCreateATranslatorOrReadProviders(): void
    {
        $kernel = $this->boot(['translator' => false], [$this->project . '/missing']);
        self::assertFalse($kernel->getContainer()->has('translator'));
        self::assertFalse($kernel->getContainer()->has(TranslatorInterface::class));
    }

    public function testInvalidProviderDirectoriesKeepTheRegistrarException(): void
    {
        $this->expectException(TranslatableInvalidArgumentException::class);
        $this->expectExceptionMessage('does not exist');
        $this->boot(directories: [$this->project . '/missing']);
        $this->translator()->trans('shared');
    }

    public function testInvalidProviderFilesKeepTheRegistrarException(): void
    {
        file_put_contents($this->project . '/packages/first/invalid.php', '<?php return [];');
        $this->expectException(TranslatableInvalidArgumentException::class);
        $this->expectExceptionMessage('naming convention');
        $this->boot();
        $this->translator()->trans('shared');
    }

    public function testNativeCacheWarmingIncludesProviderOnlyLocalesAndSurvivesReboot(): void
    {
        $kernel = $this->boot(debug: false);
        $native = $kernel->getContainer()->get('test.native_translator');
        self::assertInstanceOf(Translator::class, $native);
        $native->warmUp($kernel->getCacheDir());
        $cacheFiles = glob($kernel->getCacheDir() . '/translations/catalogue.fr.*.php');
        self::assertNotFalse($cacheFiles);
        self::assertNotEmpty($cacheFiles);
        $kernel->shutdown();
        $kernel->boot();
        self::assertSame('application', $this->translator()->trans('shared'));
        self::assertSame('French package Ada', $this->translator()->trans('provider_locale', ['name' => 'Ada'], locale: 'fr'));
    }

    public function testDebugCacheDetectsChangesInsideProviderDirectories(): void
    {
        $kernel = $this->boot();
        self::assertSame('first only', $this->translator()->trans('first_only'));
        $kernel->shutdown();
        $file = $this->project . '/packages/first/messages+intl-icu.es.php';
        file_put_contents($file, "<?php return ['first_only' => 'updated package'];");
        // Ensure the edit is newer than the cache on filesystems with second resolution.
        touch($file, time() + 2);
        clearstatcache();
        // Symfony memoizes resource freshness within a PHP process. A new
        // application request must run in a fresh process to check edited files.
        self::assertSame('updated package', $this->translateInNewProcess('first_only'));
        self::assertSame('application', $this->translateInNewProcess('shared'));
    }

    public function testTheNativeLoggingDecoratorStillReceivesThePackageResources(): void
    {
        $this->boot(['translator' => ['fallbacks' => ['en'], 'logging' => true]], withLogger: true);
        self::assertInstanceOf(LoggingTranslator::class, $this->translator());
        self::assertSame('second package', $this->translator()->trans('provider_order'));
        self::assertSame('application', $this->translator()->trans('shared'));
    }

    public function testExplicitEnabledLocalesStillRestrictNativeCacheWarming(): void
    {
        $kernel = $this->boot(['enabled_locales' => ['es']], debug: false);
        $native = $kernel->getContainer()->get('test.native_translator');
        self::assertInstanceOf(Translator::class, $native);
        $native->warmUp($kernel->getCacheDir());
        self::assertSame([], glob($kernel->getCacheDir() . '/translations/catalogue.fr.*.php'));
        self::assertSame('application', $this->translator()->trans('shared'));
    }

    private function translateInNewProcess(string $key, string $domain = 'messages'): string
    {
        $process = new Process([PHP_BINARY, dirname(__DIR__) . '/fixtures/translate.php', $this->project, $key, $domain]);
        $process->mustRun();

        return $process->getOutput();
    }

    public function testDebugCacheDiscoversNewProviderFiles(): void
    {
        $kernel = $this->boot();
        self::assertSame('application', $this->translator()->trans('shared'));
        $kernel->shutdown();
        file_put_contents($this->project . '/packages/first/extra+intl-icu.es.json', '{"added": "new resource"}');
        touch($this->project . '/packages/first', time() + 2);
        clearstatcache();
        self::assertSame('new resource', $this->translateInNewProcess('added', 'extra'));
    }

    public function testDebugCacheRemovesDeletedProviderFiles(): void
    {
        $kernel = $this->boot();
        self::assertSame('second package', $this->translator()->trans('provider_order'));
        $kernel->shutdown();
        unlink($this->project . '/packages/second/messages+intl-icu.es.php');
        clearstatcache();
        self::assertSame('first package', $this->translateInNewProcess('provider_order'));
    }
}
