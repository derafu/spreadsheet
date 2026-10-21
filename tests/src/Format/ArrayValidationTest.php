<?php

declare(strict_types=1);

/**
 * Derafu: Spreadsheet - Unified Spreadsheet Processing for PHP.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsSpreadsheet\Format;

use Derafu\Spreadsheet\Contract\SpreadsheetFormatHandlerInterface;
use Derafu\Spreadsheet\Exception\SpreadsheetDumpException;
use Derafu\Spreadsheet\Exception\SpreadsheetLoadException;
use Derafu\Spreadsheet\Format\JsonHandler;
use Derafu\Spreadsheet\Format\YamlHandler;
use Derafu\Spreadsheet\Sheet;
use Derafu\Spreadsheet\Spreadsheet;
use Derafu\Spreadsheet\SpreadsheetCaster;
use Derafu\Spreadsheet\Translation\SpreadsheetTranslationResourceProvider;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException as InvalidArgumentException;
use Derafu\Translation\TranslatorFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(JsonHandler::class)]
#[CoversClass(YamlHandler::class)]
#[CoversClass(SpreadsheetCaster::class)]
#[UsesClass(Sheet::class)]
#[UsesClass(Spreadsheet::class)]
#[UsesClass(SpreadsheetTranslationResourceProvider::class)]
final class ArrayValidationTest extends TestCase
{
    private function handler(string $format): SpreadsheetFormatHandlerInterface
    {
        return $format === 'json' ? new JsonHandler() : new YamlHandler();
    }

    /** @return iterable<string, array{string, bool, bool, mixed}> */
    public static function invalidRows(): iterable
    {
        foreach (['json', 'yaml'] as $format) {
            foreach ([false, true] as $associative) {
                foreach ([false, true] as $named) {
                    foreach ([null, false, true, 12, 1.5, 'invalid', ''] as $invalid) {
                        yield "$format/associative=$associative/named=$named/" . get_debug_type($invalid) . '/' . var_export($invalid, true) => [
                            $format, $associative, $named, $invalid,
                        ];
                    }
                }
            }
        }
    }

    #[DataProvider('invalidRows')]
    public function testEveryRowIsValidatedIncludingRowsAfterTheFirst(
        string $format,
        bool $associative,
        bool $named,
        mixed $invalid,
    ): void {
        $rows = [$associative ? ['name' => 'First'] : ['First'], $invalid];
        $data = json_encode($named ? ['Records' => $rows] : $rows, JSON_THROW_ON_ERROR);
        $type = get_debug_type($invalid);

        try {
            $this->handler($format)->loadFromString($data, 'Records');
            $this->fail('A non-array row must be rejected.');
        } catch (SpreadsheetLoadException $exception) {
            $this->assertSame("Row \"1\" in sheet \"Records\" must be an array; $type given.", $exception->getMessage());
            $translator = TranslatorFactory::create('es', resourceProviders: [new SpreadsheetTranslationResourceProvider()]);
            $this->assertSame("La fila \"1\" de la hoja \"Records\" debe ser un arreglo; se recibió $type.", $exception->trans($translator));
        }
    }

    /** @return iterable<string, array{string, bool, bool}> */
    public static function validRows(): iterable
    {
        foreach (['json', 'yaml'] as $format) {
            foreach ([false, true] as $associative) {
                foreach ([false, true] as $named) {
                    yield "$format/associative=$associative/named=$named" => [$format, $associative, $named];
                }
            }
        }
    }

    #[DataProvider('validRows')]
    public function testValidationPreservesEmptyRowsAndArbitraryCellValues(string $format, bool $associative, bool $named): void
    {
        $cells = [null, false, 12, 1.5, 'text', ['nested' => ['value' => 123]]];
        $rows = [$associative ? ['cells' => $cells] : $cells, [], $associative ? ['other' => true] : [true]];
        $data = json_encode($named ? ['Records' => $rows] : $rows, JSON_THROW_ON_ERROR);

        $spreadsheet = $this->handler($format)->loadFromString($data, 'Records');

        $sheet = $spreadsheet->getSheet('Records');
        $this->assertNotNull($sheet);
        $this->assertSame($rows, $sheet->getRows());
        $this->assertSame($associative, $sheet->isAssociative());
    }

    /** @return iterable<string, array{string}> */
    public static function formats(): iterable
    {
        yield 'JSON' => ['json'];
        yield 'YAML' => ['yaml'];
    }

    #[DataProvider('formats')]
    public function testNamedSheetsKeepNonSequentialIntegerRowKeys(string $format): void
    {
        $rows = [0 => ['First'], 5 => ['Last']];
        $data = json_encode(['Records' => $rows], JSON_THROW_ON_ERROR);

        $sheet = $this->handler($format)->loadFromString($data)->getSheet('Records');

        $this->assertNotNull($sheet);
        $this->assertSame($rows, $sheet->getRows());
    }

    /** @return iterable<string, array{int}> */
    public static function invalidDepths(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-1];
        yield 'above PHP limit' => [2147483648];
        yield 'maximum PHP integer' => [PHP_INT_MAX];
    }

    #[DataProvider('invalidDepths')]
    public function testInvalidJsonDepthIsATranslatableConfigurationError(int $depth): void
    {
        try {
            new JsonHandler(depth: $depth);
            $this->fail('An invalid JSON depth must be rejected during construction.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame("The JSON depth must be between 1 and 2147483647; $depth given.", $exception->getMessage());
            $translator = TranslatorFactory::create('es', resourceProviders: [new SpreadsheetTranslationResourceProvider()]);
            $this->assertSame("La profundidad JSON debe estar entre 1 y 2147483647; se recibió $depth.", $exception->trans($translator));
        }
    }

    public function testBothJsonDepthBoundariesAreAccepted(): void
    {
        foreach ([1, 2147483647] as $depth) {
            $sheet = (new JsonHandler(depth: $depth))->loadFromString('42')->getSheet('Sheet');
            $this->assertNotNull($sheet);
            $this->assertSame([[42]], $sheet->getRows());
        }
    }

    public function testJsonFileFallbackStillWrapsAnEmptyStructureAsOneRow(): void
    {
        foreach (['[]', '{}'] as $data) {
            $path = tempnam(sys_get_temp_dir(), 'spreadsheet-validation-');
            $this->assertIsString($path);
            try {
                $this->assertSame(strlen($data), file_put_contents($path, $data));
                $spreadsheet = (new JsonHandler())->loadFromFile($path);
                $sheet = $spreadsheet->getSheet(pathinfo($path, PATHINFO_FILENAME));
                $this->assertNotNull($sheet);
                $this->assertSame([[]], $sheet->getRows());
            } finally {
                unlink($path);
            }
        }
    }

    /** @return iterable<string, array{bool, mixed, int}> */
    public static function unencodableValues(): iterable
    {
        foreach ([false, true] as $associative) {
            foreach (['invalid UTF-8' => "\xff", 'infinity' => INF, 'not a number' => NAN] as $name => $value) {
                foreach ([0, JSON_PARTIAL_OUTPUT_ON_ERROR, JSON_THROW_ON_ERROR] as $options) {
                    yield "associative=$associative/$name/options=$options" => [$associative, $value, $options];
                }
            }
        }
    }

    #[DataProvider('unencodableValues')]
    public function testJsonEncodingFailuresAreTranslatableForBothOutputStructures(bool $associative, mixed $value, int $options): void
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->createSheet('Records', [$associative ? ['value' => $value] : [$value]], $associative);

        try {
            (new JsonHandler(encodeOptions: $options))->dumpToString($spreadsheet);
            $this->fail('A JSON encoding failure must be reported.');
        } catch (SpreadsheetDumpException $exception) {
            $prefix = $options === JSON_THROW_ON_ERROR ? 'Error creating JSON string: ' : 'Failed to encode data as JSON: ';
            $this->assertStringStartsWith($prefix, $exception->getMessage());
            $translator = TranslatorFactory::create('es', resourceProviders: [new SpreadsheetTranslationResourceProvider()]);
            $translatedPrefix = $options === JSON_THROW_ON_ERROR ? 'Error al crear el string JSON: ' : 'No se pudieron codificar los datos como JSON: ';
            $this->assertStringStartsWith($translatedPrefix, $exception->trans($translator));
        }
    }

    public function testTheCasterDecodesOnlyArraysAndPreservesOtherStrings(): void
    {
        $values = ['[]', '{}', '[1, {"nested": true}]', '{broken', '[broken', '"plain"'];
        $spreadsheet = new Spreadsheet();
        $spreadsheet->createSheet('Records', [$values]);

        $sheet = (new SpreadsheetCaster())->castAfterLoad($spreadsheet)->getSheet('Records');

        $this->assertNotNull($sheet);
        $this->assertSame([[[], [], [1, ['nested' => true]], '{broken', '[broken', '"plain"']], $sheet->getRows());
    }
}
