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

use Derafu\Spreadsheet\Exception\SpreadsheetDumpException;
use Derafu\Spreadsheet\Format\CsvLeagueHandler;
use Derafu\Spreadsheet\Format\JsonHandler;
use Derafu\Spreadsheet\Format\XmlHandler;
use Derafu\Spreadsheet\Format\YamlHandler;
use Derafu\Spreadsheet\Sheet;
use Derafu\Spreadsheet\Spreadsheet;
use Derafu\Spreadsheet\Translation\SpreadsheetTranslationResourceProvider;
use Derafu\Translation\TranslatorFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SimpleXMLElement;
use stdClass;
use Symfony\Component\Yaml\Yaml;

#[CoversClass(JsonHandler::class)]
#[CoversClass(YamlHandler::class)]
#[CoversClass(XmlHandler::class)]
#[CoversClass(CsvLeagueHandler::class)]
#[UsesClass(Spreadsheet::class)]
#[UsesClass(Sheet::class)]
#[UsesClass(SpreadsheetTranslationResourceProvider::class)]
final class AdditionalStructuresTest extends TestCase
{
    public function testNamedSheetMapsSkipEmptyAndNonArrayEntriesWithoutDiscardingValidSheets(): void
    {
        $data = '{"Empty":[],"Metadata":"description","Products":[["Laptop",12]],"AlsoEmpty":[]}';
        foreach ([new JsonHandler(), new YamlHandler()] as $handler) {
            $loaded = $handler->loadFromString($data);

            $this->assertSame(['Products'], $loaded->getSheetNames());
            $this->assertSame([['Laptop', 12]], $loaded->getActiveSheet()->getRows());
        }
    }

    public function testXmlSheetsWithoutNamesRemainIndividuallyAccessibleByTheirGeneratedNames(): void
    {
        $xml = '<spreadsheet><sheet><row><col>First</col></row></sheet><sheet><row><col>Second</col></row></sheet></spreadsheet>';

        $loaded = (new XmlHandler())->loadFromString($xml);

        $this->assertCount(2, $loaded);
        $first = $loaded->getSheet('1');
        $second = $loaded->getSheet('2');
        $this->assertNotNull($first);
        $this->assertNotNull($second);
        $this->assertSame('1', $first->getName());
        $this->assertSame('2', $second->getName());
        $this->assertSame([['First']], $first->getRows());
        $this->assertSame([['Second']], $second->getRows());
    }

    /**
     * @param array<int|string, mixed> $value
     */
    #[DataProvider('xmlArrayValues')]
    public function testXmlArraysRoundTripWithoutACaster(array $value): void
    {
        $source = new Spreadsheet();
        $source->createSheet('Records', [[$value]]);
        $handler = new XmlHandler();

        $xml = $handler->dumpToString($source);
        $loaded = $handler->loadFromString($xml);
        $this->assertSame([[$value]], $loaded->getActiveSheet()->getRows());
        $this->assertSame([[$value]], $source->getActiveSheet()->getRows());
        $this->assertSame(
            [[$value]],
            $handler->loadFromString($handler->dumpToString($loaded))->getActiveSheet()->getRows()
        );

        if (array_is_list($value)) {
            $cell = (new SimpleXMLElement($xml))->sheet->row->col;
            foreach ($cell->children() as $item) {
                $this->assertFalse(isset($item['key']));
            }
        }
    }

    /**
     * @return iterable<string, array{array<int|string, mixed>}>
     */
    public static function xmlArrayValues(): iterable
    {
        yield 'empty' => [[]];
        yield 'list' => [[true, false, null, 0, 1.5, '', 'Text']];
        yield 'associative and nested' => [['stock' => 3, 'values' => [1, 2], 'nested' => ['active' => true, 'optional' => null]]];
        yield 'sparse numeric keys' => [[0 => 'First', 2 => 'Third', 8 => []]];
        yield 'mixed keys' => [[2 => 'First', 'name' => 'Product', 0 => 'Last']];
        yield 'empty and numeric string keys' => [['' => null, 0 => 'Zero', '01' => false, -2 => 'Negative']];
        yield 'nested lists' => [[[1, 2], [], ['nested' => ['value' => 'Text']]]];
    }

    public function testXmlNestedObjectPropertiesRetainTheirValuesAsArrays(): void
    {
        $source = new Spreadsheet();
        $source->createSheet('Records', [[(object)[
            'name' => 'Product',
            'metadata' => ['stock' => 3, 'values' => [1, 2]],
            'details' => (object)['active' => true, 'optional' => null],
        ]]]);
        $handler = new XmlHandler();

        $loaded = $handler->loadFromString($handler->dumpToString($source));

        $this->assertSame([[['name' => 'Product', 'metadata' => ['stock' => 3, 'values' => [1, 2]], 'details' => ['active' => true, 'optional' => null]]]], $loaded->getActiveSheet()->getRows());
    }

    public function testXmlReadsLegacyUnkeyedArraysAndOnlyUnwrapsTypedValueElements(): void
    {
        $xml = <<<'XML'
            <spreadsheet>
                <sheet name="Records">
                    <row>
                        <col type="array"><item type="integer">1</item><item type="boolean">false</item><item type="null"/></col>
                        <col><value type="array"><item type="integer">2</item></value></col>
                        <col type="string"><value type="integer">3</value></col>
                        <col><value>Untyped</value></col>
                        <col><value type="integer">4</value><value type="integer">5</value></col>
                        <col>Text<value type="integer">6</value></col>
                    </row>
                </sheet>
            </spreadsheet>
            XML;

        $loaded = (new XmlHandler())->loadFromString($xml);

        $this->assertSame([[[1, false, null], [2], '', '', '', 'Text']], $loaded->getActiveSheet()->getRows());
    }

    public function testNamedSingleRowsAndEmptyRowsAreRecognizedInBothArrayFormats(): void
    {
        foreach ([new JsonHandler(), new YamlHandler()] as $handler) {
            $single = $handler->loadFromString('{"Products":{"name":"Laptop","price":100}}');
            $this->assertSame(['Products'], $single->getSheetNames());
            $this->assertSame([['name' => 'Laptop', 'price' => 100]], $single->getActiveSheet()->getRows());
            $this->assertTrue($single->getActiveSheet()->isAssociative());
            $emptyRow = $handler->loadFromString('[[]]');
            $this->assertSame([[]], $emptyRow->getActiveSheet()->getRows());
            $this->assertFalse($emptyRow->getActiveSheet()->isAssociative());
        }
    }

    public function testYamlScalarValuesAreWrappedWithoutAdditionalTypeInference(): void
    {
        $handler = new YamlHandler();
        foreach (['42' => 42, 'false' => false, 'null' => null, '"plain text"' => 'plain text'] as $yaml => $expected) {
            $this->assertSame([[$expected]], $handler->loadFromString((string)$yaml)->getActiveSheet()->getRows());
        }
    }

    public function testYamlDumpErrorsAreTranslatable(): void
    {
        $source = new Spreadsheet();
        $source->createSheet('Records', [[new stdClass()]]);
        try {
            (new YamlHandler(dumpFlags: Yaml::DUMP_EXCEPTION_ON_INVALID_TYPE))->dumpToString($source);
            $this->fail('A YAML configuration that forbids objects must reject an object cell.');
        } catch (SpreadsheetDumpException $exception) {
            $this->assertStringStartsWith('Error creating YAML string:', $exception->getMessage());
            $translator = TranslatorFactory::create('es', resourceProviders: [new SpreadsheetTranslationResourceProvider()]);
            $this->assertStringStartsWith('Error al crear el string YAML:', $exception->trans($translator));
        }
    }

    public function testFlatXmlRecordsCollectColumnsAndReadExplicitObjectTypes(): void
    {
        $loaded = (new XmlHandler())->loadFromFile(dirname(__DIR__, 2) . '/fixtures/xml/xml-flat-records.xml');
        $this->assertSame(['catalog'], $loaded->getSheetNames());
        $this->assertSame([
            ['name' => 'Ñandú & Desktop', 'price' => 12, 'metadata' => ['stock' => 3, 'active' => true], 'note' => null],
            ['name' => 'Second', 'price' => null, 'metadata' => null, 'note' => 'Additional column'],
        ], $loaded->getActiveSheet()->getRows());
    }

    public function testXmlCanReadRecordsWhoseWrapperAndItemHaveTheSameName(): void
    {
        $loaded = (new XmlHandler())->loadFromString('<entry><entry><name>First</name></entry><entry><name>Second</name></entry></entry>');
        $this->assertSame(['entry_sheet'], $loaded->getSheetNames());
        $this->assertSame([['name' => 'First'], ['name' => 'Second']], $loaded->getActiveSheet()->getRows());
    }

    public function testXmlNamesAndTextAreSafelySerialized(): void
    {
        $source = new Spreadsheet();
        $source->createSheet('Records', [['123 value' => 'Text', '' => '<p>A & B</p>']], true);
        $xml = (new XmlHandler())->dumpToString($source);
        $this->assertStringContainsString('<col_123_value>Text</col_123_value>', $xml);
        $this->assertStringContainsString('<col_><![CDATA[<p>A & B</p>]]></col_>', $xml);
    }

    public function testAnEmptyCsvSpreadsheetSerializesAsEmptyText(): void
    {
        $this->assertSame('', (new CsvLeagueHandler())->dumpToString(new Spreadsheet()));
    }

    public function testXmlSerializationIncludesAssociativeArrayKeysAndNestedValues(): void
    {
        $source = new Spreadsheet();
        $source->createSheet('Records', [[['stock' => 3, 'values' => [1, 2]]]]);

        $xml = new SimpleXMLElement((new XmlHandler())->dumpToString($source));
        $cell = $xml->sheet->row->col;

        $this->assertSame('array', (string)$cell['type']);
        $this->assertSame('stock', (string)$cell->item[0]['key']);
        $this->assertSame('integer', (string)$cell->item[0]['type']);
        $this->assertSame('3', (string)$cell->item[0]);
        $this->assertSame('values', (string)$cell->item[1]['key']);
        $this->assertSame('array', (string)$cell->item[1]->value['type']);
        $this->assertSame('1', (string)$cell->item[1]->value->item[0]);
        $this->assertSame('2', (string)$cell->item[1]->value->item[1]);
    }
}
