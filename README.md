# Derafu: Spreadsheet - Unified Spreadsheet Processing for PHP

![GitHub last commit](https://img.shields.io/github/last-commit/derafu/spreadsheet/main)
![CI Workflow](https://github.com/derafu/spreadsheet/actions/workflows/ci.yml/badge.svg?branch=main&event=push)
![GitHub code size in bytes](https://img.shields.io/github/languages/code-size/derafu/spreadsheet)
![GitHub Issues](https://img.shields.io/github/issues-raw/derafu/spreadsheet)
![Total Downloads](https://poser.pugx.org/derafu/spreadsheet/downloads)
![Monthly Downloads](https://poser.pugx.org/derafu/spreadsheet/d/monthly)

Derafu Spreadsheet is a modern PHP library that provides a **single, consistent API** for working with spreadsheet files in multiple formats (XLSX, CSV, ODS, JSON, XML, YAML, and more).

## 🌟 Features

- **Unified API** across all file formats.
- **Multiple format support**: XLSX, XLS, CSV, ODS, JSON, XML, YAML, HTML, PDF.
- **Smart type casting**: Automatically detects and converts data types (dates, numbers, booleans, JSON).
- **Format-agnostic data manipulation**: Work with your data consistently regardless of source format.
- **Minimal dependencies**: Use only what you need.
- **PSR-7 compatible**: Generate HTTP responses with downloadable spreadsheets.
- **Modern PHP**: Written for PHP 8 with strict typing.

## 🚀 Why Derafu Spreadsheet?

While powerful libraries like PhpSpreadsheet exist, and this library use it under de hood, they often require different approaches for different formats and have complex APIs. Derafu Spreadsheet offers several key advantages:

- **Simplified API**: Work with all spreadsheet formats through a consistent interface.
- **Intelligent type handling**: Focus on your data, not type conversion.
- **Format abstraction**: Write your code once, and it works with any format.
- **Flexible format handlers**: Easily switch between formats or implement custom handlers.
- **Minimal learning curve**: Clean, intuitive API with sensible defaults.

## 📦 Installation

```bash
composer require derafu/spreadsheet
```

For specific format support, you may need additional dependencies:

```bash
# For CSV support with League CSV (recommended).
composer require league/csv

# For XLSX, XLS, ODS, HTML support.
composer require phpoffice/phpspreadsheet

# For YAML support.
composer require symfony/yaml

# For HTTP response generation.
composer require nyholm/psr7
```

## 📝 Basic Usage

```php
<?php

use Derafu\Spreadsheet\SpreadsheetLoader;
use Derafu\Spreadsheet\SpreadsheetDumper;

// Load a spreadsheet (format auto-detected from extension).
// Under the hood it will create default Factory and Caster instances. If you
// don't want that, inject your implementation.
$loader = new Loader();
$spreadsheet = $loader->loadFromFile('data.xlsx');

// Access data from sheets.
$sheet = $spreadsheet->getSheet('Sheet1');
$rows = $sheet->getRows();

// Modify data.
$sheet->setCell(0, 0, 'Updated value');
$spreadsheet->createSheet('NewSheet', [['Header1', 'Header2'], [1, 2]]);

// Save in different format.
// Under the hood it will create default Factory and Caster instances. If you
// don't want that, inject your implementation.
$dumper = new Dumper();
$dumper->dumpToFile($spreadsheet, 'output.csv');

// Or convert to string
$jsonString = $dumper->dumpToString($spreadsheet, 'json');
```

## 🔄 Working with Different Formats

Derafu Spreadsheet handles format conversion automatically:

```php
// Load Excel file.
$spreadsheet = $loader->loadFromFile('data.xlsx');

// Save as CSV.
$dumper->dumpToFile($spreadsheet, 'data.csv');

// Save as JSON.
$dumper->dumpToFile($spreadsheet, 'data.json');

// Save as YAML.
$dumper->dumpToFile($spreadsheet, 'data.yaml');
```

## 📋 Intelligent Type Casting

One of Derafu Spreadsheet's key features is automatic type casting for both reading and writing:

```php
$spreadsheet = $loader->loadFromFile('data.csv');

// String '123' is automatically cast to integer 123.
// 'true' is cast to boolean true.
// '2025-03-12' is cast to DateTimeImmutable object.
// JSON strings are parsed to indexed arrays or associative arrays ("objects").

$cell = $sheet->getCell(0, 0); // Typed data, not just strings.

// When writing, types are automatically converted to appropriate formats.
$dumper->dumpToFile($spreadsheet, 'output.xlsx');
```

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request. For major changes, please open an issue first to discuss what you would like to change.

## License

This package is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
