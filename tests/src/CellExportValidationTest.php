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

use Derafu\Spreadsheet\Exception\SpreadsheetDumpException;
use Derafu\Spreadsheet\Format\CsvLeagueHandler;
use Derafu\Spreadsheet\Format\XmlHandler;
use Derafu\Spreadsheet\Sheet;
use Derafu\Spreadsheet\Spreadsheet;
use Derafu\Spreadsheet\SpreadsheetCaster;
use Derafu\Spreadsheet\Translation\SpreadsheetTranslationResourceProvider;
use Derafu\Translation\TranslatorFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Stringable;

#[CoversClass(SpreadsheetCaster::class)]
#[CoversClass(CsvLeagueHandler::class)]
#[CoversClass(XmlHandler::class)]
#[UsesClass(Spreadsheet::class)]
#[UsesClass(Sheet::class)]
#[UsesClass(SpreadsheetTranslationResourceProvider::class)]
final class CellExportValidationTest extends TestCase
{
    /** @param callable(): mixed $action */
    private function assertTranslatedFailure(callable $action, string $english, string $spanish, bool $wrapped = false): void
    {
        try {
            $action();
            $this->fail('An unsupported cell value must be rejected.');
        } catch (SpreadsheetDumpException $exception) {
            if ($wrapped) {
                $previous = $exception->getPrevious();
                $this->assertInstanceOf(SpreadsheetDumpException::class, $previous);
                $exception = $previous;
            }
            $this->assertSame($english, $exception->getMessage());
            $translator = TranslatorFactory::create('es', resourceProviders: [new SpreadsheetTranslationResourceProvider()]);
            $this->assertSame($spanish, $exception->trans($translator));
        }
    }

    public function testTheCasterRejectsResourcesWithoutChangingEarlierCells(): void
    {
        $resource = fopen('php://temp', 'w+');
        $this->assertIsResource($resource);
        try {
            $source = new Spreadsheet();
            $source->createSheet('Records', [[true, $resource]]);
            $original = $source->toArray();
            $type = get_debug_type($resource);
            $this->assertTranslatedFailure(
                fn () => (new SpreadsheetCaster())->castBeforeDump($source),
                "Unsupported cell type for writing: $type.",
                "Tipo de celda no soportado para escritura: $type.",
            );
            $this->assertSame($original, $source->toArray());
        } finally {
            fclose($resource);
        }
    }

    /** @return iterable<string, array{mixed, string}> */
    public static function unencodableCells(): iterable
    {
        yield 'invalid UTF-8 inside an array' => [["\xff"], 'Malformed UTF-8 characters, possibly incorrectly encoded'];
        yield 'infinity inside an array' => [[INF], 'Inf and NaN cannot be JSON encoded'];
        yield 'not a number inside an object' => [(object) ['value' => NAN], 'Inf and NaN cannot be JSON encoded'];
        $recursive = new stdClass();
        $recursive->self = $recursive;
        yield 'recursive object' => [$recursive, 'Recursion detected'];
    }

    #[DataProvider('unencodableCells')]
    public function testTheCasterRejectsJsonEncodingFailuresInsteadOfReturningFalse(mixed $cell, string $error): void
    {
        $source = new Spreadsheet();
        $source->createSheet('Records', [[true, $cell]]);
        $original = $source->toArray();

        $this->assertTranslatedFailure(
            fn () => (new SpreadsheetCaster())->castBeforeDump($source),
            "Failed to encode a cell value as JSON: $error",
            "No se pudo codificar el valor de una celda como JSON: $error",
        );
        $this->assertSame($original, $source->toArray());
    }

    public function testTheCasterRejectsAnUnsupportedValueNestedInsideAJsonCell(): void
    {
        $resource = fopen('php://temp', 'w+');
        $this->assertIsResource($resource);
        try {
            $source = new Spreadsheet();
            $source->createSheet('Records', [[['resource' => $resource]]]);
            $this->assertTranslatedFailure(
                fn () => (new SpreadsheetCaster())->castBeforeDump($source),
                'Failed to encode a cell value as JSON: Type is not supported',
                'No se pudo codificar el valor de una celda como JSON: Type is not supported',
            );
        } finally {
            fclose($resource);
        }
    }

    /** @return iterable<string, array{bool, mixed}> */
    public static function unsupportedCsvCells(): iterable
    {
        foreach ([false, true] as $associative) {
            yield "associative=$associative/empty array" => [$associative, []];
            yield "associative=$associative/array" => [$associative, ['nested' => 123]];
            yield "associative=$associative/object" => [$associative, new stdClass()];
        }
    }

    #[DataProvider('unsupportedCsvCells')]
    public function testTheCsvHandlerValidatesBothRowFormatsWithoutImplicitSerialization(bool $associative, mixed $cell): void
    {
        $source = new Spreadsheet();
        $source->createSheet('Records', [$associative ? ['value' => $cell] : [$cell]], $associative);
        $type = get_debug_type($cell);
        $column = $associative ? 'value' : '0';
        $original = $source->toArray();

        $this->assertTranslatedFailure(
            fn () => (new CsvLeagueHandler())->dumpToString($source),
            "Cell at row \"0\", column \"$column\" in sheet \"Records\" cannot be written as CSV; $type given.",
            "La celda en la fila \"0\", columna \"$column\" de la hoja \"Records\" no se puede escribir como CSV; se recibió $type.",
            true,
        );
        $this->assertSame($original, $source->toArray());
    }

    public function testRawCsvSerializationDoesNotApplyTheCastersBooleanPolicy(): void
    {
        $text = new class () implements Stringable {
            public function __toString(): string
            {
                return 'word';
            }
        };
        $source = new Spreadsheet();
        $source->createSheet('Records', [[true, false, null, 1, 1.5, 'false', $text]]);
        $handler = new CsvLeagueHandler();

        $csv = $handler->dumpToString($source);

        $this->assertSame("1,,,1,1.5,false,word\n", $csv);
        $this->assertSame([['1', '', '', '1', '1.5', 'false', 'word']], $handler->loadFromString($csv)->getActiveSheet()->getRows());
    }

    public function testBothHandlersRejectResourceCellsIncludingNestedXmlValues(): void
    {
        $resource = fopen('php://temp', 'w+');
        $this->assertIsResource($resource);
        $type = get_debug_type($resource);
        try {
            foreach ([false, true] as $associative) {
                $source = new Spreadsheet();
                $source->createSheet('Records', [$associative ? ['value' => $resource] : [$resource]], $associative);
                $column = $associative ? 'value' : '0';
                $this->assertTranslatedFailure(
                    fn () => (new CsvLeagueHandler())->dumpToString($source),
                    "Cell at row \"0\", column \"$column\" in sheet \"Records\" cannot be written as CSV; $type given.",
                    "La celda en la fila \"0\", columna \"$column\" de la hoja \"Records\" no se puede escribir como CSV; se recibió $type.",
                    true,
                );
            }
            foreach ([$resource, [$resource], (object) ['value' => $resource]] as $cell) {
                $source = new Spreadsheet();
                $source->createSheet('Records', [[$cell]]);
                $this->assertTranslatedFailure(
                    fn () => (new XmlHandler())->dumpToString($source),
                    "Unsupported cell type for XML: $type.",
                    "Tipo de celda no soportado para XML: $type.",
                    true,
                );
            }
        } finally {
            fclose($resource);
        }
    }
}
