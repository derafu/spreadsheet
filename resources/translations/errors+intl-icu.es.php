<?php

declare(strict_types=1);

/**
 * Derafu: Spreadsheet - Unified Spreadsheet Processing for PHP.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

return [
    'The JSON depth must be between 1 and 2147483647; {depth} given.' =>
        'La profundidad JSON debe estar entre 1 y 2147483647; se recibió {depth}.',
    'Row "{index}" in sheet "{sheet}" must be an array; {type} given.' =>
        'La fila "{index}" de la hoja "{sheet}" debe ser un arreglo; se recibió {type}.',
    'Failed to serialize the XML document.' =>
        'No se pudo serializar el documento XML.',
    // Files.
    'File "{filepath}" not found.' =>
        'No se encontró el archivo "{filepath}".',
    'File not found: "{filepath}".' =>
        'Archivo no encontrado: "{filepath}".',
    'File not readable: "{filepath}".' =>
        'El archivo no se puede leer: "{filepath}".',
    'Could not read file: "{filepath}".' =>
        'No se pudo leer el archivo: "{filepath}".',
    'Could not write to file: "{filepath}".' =>
        'No se pudo escribir en el archivo: "{filepath}".',
    'Directory "{directory}" could not be created.' =>
        'No se pudo crear el directorio "{directory}".',
    'Could not create temporary file for loading from string.' =>
        'No se pudo crear el archivo temporal para cargar desde un string.',
    'Could not create temporary file for dumping to string.' =>
        'No se pudo crear el archivo temporal para volcar a un string.',
    'Could not read temporary file after writing.' =>
        'No se pudo leer el archivo temporal después de escribirlo.',

    // Formats.
    'Format "{format}" is not supported.' =>
        'El formato "{format}" no está soportado.',
    'Could not detect extension for file "{filepath}".' =>
        'No se pudo detectar la extensión del archivo "{filepath}".',
    'Extension "{extension}" is not supported.' =>
        'La extensión "{extension}" no está soportada.',
    'PDF files are not supported for reading, yet.' =>
        'Los archivos PDF aún no están soportados para lectura.',
    'Nyholm PSR-7 implementation is required. Install with "composer require nyholm/psr7".' =>
        'Se requiere la implementación PSR-7 de Nyholm. Instálala con "composer require nyholm/psr7".',

    // Reading.
    'Error reading CSV file: {message}' =>
        'Error al leer el archivo CSV: {message}',
    'Error reading JSON file: {message}' =>
        'Error al leer el archivo JSON: {message}',
    'Error reading XML file: {message}' =>
        'Error al leer el archivo XML: {message}',
    'Error reading YAML file: {message}' =>
        'Error al leer el archivo YAML: {message}',
    'Error reading spreadsheet: "{message}".' =>
        'Error al leer la planilla: "{message}".',
    'Error reading spreadsheet from string: "{message}".' =>
        'Error al leer la planilla desde un string: "{message}".',
    'Error processing CSV data: {message}' =>
        'Error al procesar los datos CSV: {message}',
    'Error processing JSON data: {message}' =>
        'Error al procesar los datos JSON: {message}',
    'Error processing YAML data: {message}' =>
        'Error al procesar los datos YAML: {message}',
    'Invalid JSON data. Error: {error}' =>
        'Datos JSON inválidos. Error: {error}',
    'Invalid XML data. Error: {error}' =>
        'Datos XML inválidos. Error: {error}',
    'Invalid YAML data. Error: {error}' =>
        'Datos YAML inválidos. Error: {error}',

    // Writing.
    'Column index "{column}" at row "{row}" in sheet "{sheet}" must be a non-negative integer.' =>
        'El índice de columna "{column}" en la fila "{row}" de la hoja "{sheet}" debe ser un entero no negativo.',
    'Could not prepare XML element name for property "{property}".' =>
        'No se pudo preparar el nombre del elemento XML para la propiedad "{property}".',
    'Failed to encode a cell value as JSON: {error}' =>
        'No se pudo codificar el valor de una celda como JSON: {error}',
    'Unsupported cell type for writing: {type}.' =>
        'Tipo de celda no soportado para escritura: {type}.',
    'Unsupported cell type for XML: {type}.' =>
        'Tipo de celda no soportado para XML: {type}.',
    'Cell at row "{row}", column "{column}" in sheet "{sheet}" cannot be written as CSV; {type} given.' =>
        'La celda en la fila "{row}", columna "{column}" de la hoja "{sheet}" no se puede escribir como CSV; se recibió {type}.',
    'Error writing CSV file: {message}' =>
        'Error al escribir el archivo CSV: {message}',
    'Error writing JSON file: {message}' =>
        'Error al escribir el archivo JSON: {message}',
    'Error writing XML file: {message}' =>
        'Error al escribir el archivo XML: {message}',
    'Error writing YAML file: {message}' =>
        'Error al escribir el archivo YAML: {message}',
    'Error writing spreadsheet: "{message}".' =>
        'Error al escribir la planilla: "{message}".',
    'Error creating CSV string: {message}' =>
        'Error al crear el string CSV: {message}',
    'Error creating JSON string: {message}' =>
        'Error al crear el string JSON: {message}',
    'Error creating XML string: {message}' =>
        'Error al crear el string XML: {message}',
    'Error creating YAML string: {message}' =>
        'Error al crear el string YAML: {message}',
    'Error creating spreadsheet string: "{message}".' =>
        'Error al crear el string de la planilla: "{message}".',
    'Failed to encode data as JSON: {error}' =>
        'No se pudieron codificar los datos como JSON: {error}',

    // Spreadsheet.
    'Column header at index "{index}" in sheet "{sheet}" must be int|string; {type} given.' =>
        'El encabezado de columna con índice "{index}" de la hoja "{sheet}" debe ser int|string; se recibió {type}.',
    'Sheet "{name}" does not exist.' =>
        'La hoja "{name}" no existe.',
    'No active sheet has been set.' =>
        'No se ha definido una hoja activa.',
];
