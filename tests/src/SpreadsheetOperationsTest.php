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

use Derafu\Spreadsheet\Sheet;
use Derafu\Spreadsheet\Spreadsheet;
use Derafu\Spreadsheet\Translation\SpreadsheetTranslationResourceProvider;
use Derafu\Translation\Exception\Core\TranslatableRuntimeException as RuntimeException;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException as InvalidArgumentException;
use Derafu\Translation\TranslatorFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Spreadsheet::class)]
#[UsesClass(Sheet::class)]
#[UsesClass(SpreadsheetTranslationResourceProvider::class)]
final class SpreadsheetOperationsTest extends TestCase
{
    public function testConstructorReplacementRemovalAndActiveSheetLifecycle(): void
    {
        $first = new Sheet('First', [['first']]);
        $second = new Sheet('Second', [['second']]);
        $spreadsheet = new Spreadsheet(['First' => $first, 'Second' => $second]);
        $this->assertSame($first, $spreadsheet->getActiveSheet());
        $this->assertSame(['First' => $first, 'Second' => $second], iterator_to_array($spreadsheet));
        $this->assertCount(2, $spreadsheet);
        $this->assertSame($spreadsheet, $spreadsheet->setActiveSheet('Second'));
        $this->assertSame($second, $spreadsheet->getActiveSheet());
        $replacement = new Sheet('Second', [['replacement']]);
        $this->assertSame($spreadsheet, $spreadsheet->addSheet($replacement));
        $this->assertSame($replacement, $spreadsheet->getActiveSheet());
        $this->assertCount(2, $spreadsheet);
        $this->assertSame($spreadsheet, $spreadsheet->removeSheet('Missing'));
        $this->assertSame($spreadsheet, $spreadsheet->removeSheet('First'));
        $this->assertSame($replacement, $spreadsheet->getActiveSheet());
        $spreadsheet->addSheet($first);
        $spreadsheet->removeSheet('Second');
        $this->assertSame($first, $spreadsheet->getActiveSheet());
        $spreadsheet->removeSheet('First');
        $this->assertSame([], $spreadsheet->getSheets());
        $this->assertFalse($spreadsheet->hasSheet('First'));
        $this->assertNull($spreadsheet->getSheet('First'));
        $third = $spreadsheet->createSheet('Third');
        $this->assertSame($third, $spreadsheet->getActiveSheet());
    }

    public function testFromArrayKeepsAllSheetsRowsAndTheirOrder(): void
    {
        $data = ['Products' => [['name', 'price'], ['Laptop', 100]], 'Settings' => [['enabled' => false]]];
        $spreadsheet = Spreadsheet::fromArray($data);
        $this->assertSame(['Products', 'Settings'], $spreadsheet->getSheetNames());
        foreach ($data as $name => $rows) {
            $sheet = $spreadsheet->getSheet($name);
            $this->assertNotNull($sheet);
            $this->assertSame($rows, $sheet->getRows());
        }
    }

    public function testMissingActiveSheetIsATranslatableError(): void
    {
        $translator = TranslatorFactory::create('es', resourceProviders: [new SpreadsheetTranslationResourceProvider()]);
        try {
            (new Spreadsheet())->getActiveSheet();
            $this->fail('An empty spreadsheet has no active sheet.');
        } catch (RuntimeException $exception) {
            $this->assertSame('No active sheet has been set.', $exception->getMessage());
            $this->assertSame('No se ha definido una hoja activa.', $exception->trans($translator));
        }
    }

    public function testAnUnknownSheetCannotBecomeActive(): void
    {
        $spreadsheet = new Spreadsheet();
        $original = $spreadsheet->createSheet('Products');
        try {
            $spreadsheet->setActiveSheet('Unknown');
            $this->fail('An unknown sheet cannot become active.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('Sheet "Unknown" does not exist.', $exception->getMessage());
            $translator = TranslatorFactory::create('es', resourceProviders: [new SpreadsheetTranslationResourceProvider()]);
            $this->assertSame('La hoja "Unknown" no existe.', $exception->trans($translator));
        }
        $this->assertSame($original, $spreadsheet->getActiveSheet());
    }
}
