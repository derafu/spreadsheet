<?php

declare(strict_types=1);

/**
 * Derafu: Spreadsheet - Unified Spreadsheet Processing for PHP.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsSpreadsheet;

use Derafu\Spreadsheet\Contract\Http\SpreadsheetHttpResponseGeneratorInterface;
use Derafu\Spreadsheet\Contract\SpreadsheetCasterInterface;
use Derafu\Spreadsheet\Contract\SpreadsheetDumperInterface;
use Derafu\Spreadsheet\Contract\SpreadsheetFactoryInterface;
use Derafu\Spreadsheet\Contract\SpreadsheetLoaderInterface;
use Derafu\Spreadsheet\Exception\SpreadsheetFormatNotSupportedException;
use Derafu\Spreadsheet\Translation\SpreadsheetTranslationResourceProvider;
use Derafu\TestsSpreadsheet\Fixture\PrefixingCaster;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;

/**
 * Exercises shared spreadsheet services through the real production YAML.
 */
#[CoversNothing]
final class ServicesTest extends TestCase
{
    private function container(string $configuration = 'services.yaml'): ContainerBuilder
    {
        $root = dirname(__DIR__, 2);
        $container = new ContainerBuilder();
        $loader = new YamlFileLoader($container, new FileLocator($root . '/tests/config'));
        $loader->load($configuration);
        $this->assertArrayHasKey(
            SpreadsheetTranslationResourceProvider::class,
            $container->findTaggedServiceIds('derafu_translation.resource_provider')
        );
        foreach ($container->getDefinitions() as $definition) {
            $definition->setPublic(true);
        }
        $container->compile();

        return $container;
    }

    public function testConfigurationConstructsTheTranslationProvider(): void
    {
        $provider = $this->container()->get(SpreadsheetTranslationResourceProvider::class);
        $this->assertInstanceOf(SpreadsheetTranslationResourceProvider::class, $provider);
        $directories = [...$provider->getDirectories()];
        $this->assertCount(1, $directories);
        $this->assertSame(realpath(dirname(__DIR__, 2) . '/resources/translations'), realpath($directories[0]));
    }

    public function testTaggedResourcesTranslateARealUnsupportedExtensionException(): void
    {
        $translator = $this->container()->get('app.translator');
        $this->assertInstanceOf(Translator::class, $translator);
        try {
            $factory = $this->container()->get(SpreadsheetFactoryInterface::class);
            $this->assertInstanceOf(SpreadsheetFactoryInterface::class, $factory);
            $factory->detectFormat('document.txt');
            $this->fail('An unsupported spreadsheet extension was accepted.');
        } catch (SpreadsheetFormatNotSupportedException $exception) {
            $this->assertSame('La extensión "txt" no está soportada.', $exception->trans($translator));
        }
    }

    /** @return iterable<string, array{string}> */
    public static function formats(): iterable
    {
        foreach (['json', 'csv', 'xlsx', 'yaml'] as $format) {
            yield $format => [$format];
        }
    }

    #[DataProvider('formats')]
    public function testConfiguredServicesPerformRealRoundTrips(string $format): void
    {
        $container = $this->container();
        $factory = $container->get(SpreadsheetFactoryInterface::class);
        $this->assertInstanceOf(SpreadsheetFactoryInterface::class, $factory);
        $loader = $container->get(SpreadsheetLoaderInterface::class);
        $this->assertInstanceOf(SpreadsheetLoaderInterface::class, $loader);
        $dumper = $container->get(SpreadsheetDumperInterface::class);
        $this->assertInstanceOf(SpreadsheetDumperInterface::class, $dumper);
        $source = $factory->create(['Products' => [['Laptop', 100], ['Mouse', 20]]]);
        $data = $dumper->dumpToString($source, $format);
        $loaded = $loader->loadFromString($data, $format);
        $this->assertSame($source->getActiveSheet()->getRows(), $loaded->getActiveSheet()->getRows());
        $this->assertSame(
            $loaded->getActiveSheet()->getRows(),
            $loader->loadFromString($dumper->dumpToString($loaded, $format), $format)->getActiveSheet()->getRows()
        );
        $this->assertFalse($container->has(SpreadsheetHttpResponseGeneratorInterface::class));
    }

    public function testLoaderAndDumperUseTheirConfiguredDefaultFormats(): void
    {
        $container = $this->container('json-defaults.yaml');
        $factory = $container->get(SpreadsheetFactoryInterface::class);
        $this->assertInstanceOf(SpreadsheetFactoryInterface::class, $factory);
        $loader = $container->get(SpreadsheetLoaderInterface::class);
        $this->assertInstanceOf(SpreadsheetLoaderInterface::class, $loader);
        $dumper = $container->get(SpreadsheetDumperInterface::class);
        $this->assertInstanceOf(SpreadsheetDumperInterface::class, $dumper);
        $source = $factory->create(['Products' => [['Laptop', 100]]]);
        $data = $dumper->dumpToString($source);
        $this->assertIsArray(json_decode($data, true, flags: JSON_THROW_ON_ERROR));
        $this->assertSame([['Laptop', 100]], $loader->loadFromString($data)->getActiveSheet()->getRows());
    }

    public function testNonEmptyHandlerMapReplacesTheBuiltInCatalogue(): void
    {
        $container = $this->container('custom-formats.yaml');
        $factory = $container->get(SpreadsheetFactoryInterface::class);
        $this->assertInstanceOf(SpreadsheetFactoryInterface::class, $factory);
        $this->assertSame(['records'], $factory->getSupportedFormats());
        $loader = $container->get(SpreadsheetLoaderInterface::class);
        $this->assertInstanceOf(SpreadsheetLoaderInterface::class, $loader);
        $dumper = $container->get(SpreadsheetDumperInterface::class);
        $this->assertInstanceOf(SpreadsheetDumperInterface::class, $dumper);
        $source = $factory->create(['Products' => [['Laptop', 100]]]);
        $this->assertSame([['Laptop', 100]], $loader->loadFromString($dumper->dumpToString($source))->getActiveSheet()->getRows());
    }

    public function testCasterReplacementReachesBothLoadingAndDumping(): void
    {
        $container = $this->container('custom-caster.yaml');
        $factory = $container->get(SpreadsheetFactoryInterface::class);
        $this->assertInstanceOf(SpreadsheetFactoryInterface::class, $factory);
        $caster = $container->get(SpreadsheetCasterInterface::class);
        $this->assertInstanceOf(PrefixingCaster::class, $caster);
        $loader = $container->get(SpreadsheetLoaderInterface::class);
        $this->assertInstanceOf(SpreadsheetLoaderInterface::class, $loader);
        $dumper = $container->get(SpreadsheetDumperInterface::class);
        $this->assertInstanceOf(SpreadsheetDumperInterface::class, $dumper);
        $source = $factory->create(['Products' => [['Laptop', 100]]]);
        $data = $dumper->dumpToString($source);
        $this->assertStringContainsString('stored:Laptop', $data);
        $this->assertSame([['Laptop', 100]], $loader->loadFromString($data)->getActiveSheet()->getRows());
        $this->assertSame(1, $caster->writeCalls);
        $this->assertSame(1, $caster->readCalls);
        $this->assertSame([['Laptop', 100]], $source->getActiveSheet()->getRows());
    }

    public function testOptionalHttpAdapterUsesTheSameFactoryAndCaster(): void
    {
        $container = $this->container('http.yaml');
        $factory = $container->get(SpreadsheetFactoryInterface::class);
        $this->assertInstanceOf(SpreadsheetFactoryInterface::class, $factory);
        $caster = $container->get(SpreadsheetCasterInterface::class);
        $this->assertInstanceOf(PrefixingCaster::class, $caster);
        $generator = $container->get(SpreadsheetHttpResponseGeneratorInterface::class);
        $this->assertInstanceOf(SpreadsheetHttpResponseGeneratorInterface::class, $generator);
        $response = $generator->createResponse($factory->create(['Products' => [['Laptop', 100]]]), 'products.json');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame('attachment; filename="products.json"', $response->getHeaderLine('Content-Disposition'));
        $body = (string) $response->getBody();
        $this->assertStringContainsString('stored:Laptop', $body);
        $loader = $container->get(SpreadsheetLoaderInterface::class);
        $this->assertInstanceOf(SpreadsheetLoaderInterface::class, $loader);
        $this->assertSame([['Laptop', 100]], $loader->loadFromString($body)->getActiveSheet()->getRows());
        $this->assertSame(1, $caster->writeCalls);
        $this->assertSame(1, $caster->readCalls);
    }
}
