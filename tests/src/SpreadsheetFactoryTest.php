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

use Derafu\Spreadsheet\Exception\SpreadsheetFormatNotSupportedException;
use Derafu\Spreadsheet\Format\CsvLeagueHandler;
use Derafu\Spreadsheet\Format\JsonHandler;
use Derafu\Spreadsheet\Sheet;
use Derafu\Spreadsheet\Spreadsheet;
use Derafu\Spreadsheet\SpreadsheetFactory;
use Derafu\Spreadsheet\Translation\SpreadsheetTranslationResourceProvider;
use Derafu\Translation\TranslatorFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SpreadsheetFactory::class)]
#[UsesClass(Spreadsheet::class)]
#[UsesClass(Sheet::class)]
#[UsesClass(JsonHandler::class)]
#[UsesClass(CsvLeagueHandler::class)]
#[UsesClass(SpreadsheetTranslationResourceProvider::class)]
final class SpreadsheetFactoryTest extends TestCase
{
    public function testCreatesDataAndDetectsFormatsWithoutReadingFiles(): void
    {
        $factory = new SpreadsheetFactory();
        $spreadsheet = $factory->create(['Products' => [['Laptop', 100]]]);
        $this->assertSame([['Laptop', 100]], $spreadsheet->getActiveSheet()->getRows());
        $this->assertSame('csv', $factory->detectFormat('/reports/Export.CsV'));
        $this->assertSame('xlsx', $factory->detectFormat('data.XLSX'));
        $this->assertContains('html', $factory->getSupportedFormats());
        $this->assertContains('pdf', $factory->getSupportedFormats());
        $this->assertInstanceOf(JsonHandler::class, $factory->createFormatHandler('data.unknown', 'json'));
    }

    public function testCustomHandlersCanBeRegisteredAndDefaultHandlersReplaced(): void
    {
        $factory = new SpreadsheetFactory(['records' => CsvLeagueHandler::class]);
        $this->assertSame(['records'], $factory->getSupportedFormats());
        $this->assertInstanceOf(CsvLeagueHandler::class, $factory->createFormatHandler('data.records'));
        $this->assertSame($factory, $factory->registerFormatHandler('CUSTOM', JsonHandler::class));
        $this->assertSame('custom', $factory->detectFormat('data.CUSTOM'));
        $handler = $factory->createFormatHandler('data.custom');
        $this->assertInstanceOf(JsonHandler::class, $handler);
        $this->assertSame([[42]], $handler->loadFromString('42')->getActiveSheet()->getRows());
    }

    /** @return iterable<string, array{string, string|null, string, string}> */
    public static function unsupportedFormats(): iterable
    {
        yield 'no extension' => ['report', null, 'Could not detect extension for file "report".', 'No se pudo detectar la extensión del archivo "report".'];
        yield 'unknown extension' => ['report.unknown', null, 'Extension "unknown" is not supported.', 'La extensión "unknown" no está soportada.'];
        yield 'unknown override' => ['report.csv', 'unknown', 'Format "unknown" is not supported.', 'El formato "unknown" no está soportado.'];
    }

    #[DataProvider('unsupportedFormats')]
    public function testUnsupportedFormatsAreTranslatableErrors(string $path, ?string $format, string $english, string $spanish): void
    {
        try {
            (new SpreadsheetFactory())->createFormatHandler($path, $format);
            $this->fail('An unsupported format must be rejected.');
        } catch (SpreadsheetFormatNotSupportedException $exception) {
            $this->assertSame($english, $exception->getMessage());
            $translator = TranslatorFactory::create('es', resourceProviders: [new SpreadsheetTranslationResourceProvider()]);
            $this->assertSame($spanish, $exception->trans($translator));
        }
    }
}
