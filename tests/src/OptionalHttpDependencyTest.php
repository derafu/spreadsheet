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

use Composer\Autoload\ClassLoader;
use Derafu\Spreadsheet\Exception\SpreadsheetDumpException;
use Derafu\Spreadsheet\Http\NyholmSpreadsheetHttpResponseGenerator;
use Derafu\Spreadsheet\Translation\SpreadsheetTranslationResourceProvider;
use Derafu\Translation\TranslatorFactory;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NyholmSpreadsheetHttpResponseGenerator::class)]
#[UsesClass(SpreadsheetTranslationResourceProvider::class)]
final class OptionalHttpDependencyTest extends TestCase
{
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testMissingNyholmDependencyIsATranslatableError(): void
    {
        $this->assertFalse(class_exists(Psr17Factory::class, false));
        $loader = require dirname(__DIR__, 2) . '/vendor/autoload.php';
        $this->assertInstanceOf(ClassLoader::class, $loader);
        $directories = $loader->getPrefixesPsr4()['Nyholm\\Psr7\\'];
        $loader->setPsr4('Nyholm\\Psr7\\', []);
        try {
            try {
                new NyholmSpreadsheetHttpResponseGenerator();
                $this->fail('The absent HTTP implementation must be reported.');
            } catch (SpreadsheetDumpException $exception) {
                $this->assertSame('Nyholm PSR-7 implementation is required. Install with "composer require nyholm/psr7".', $exception->getMessage());
                $translator = TranslatorFactory::create('es', resourceProviders: [new SpreadsheetTranslationResourceProvider()]);
                $this->assertSame('Se requiere la implementación PSR-7 de Nyholm. Instálala con "composer require nyholm/psr7".', $exception->trans($translator));
            }
        } finally {
            $loader->setPsr4('Nyholm\\Psr7\\', $directories);
        }
    }
}
