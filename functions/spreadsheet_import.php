<?php
// Safe reading of an uploaded spreadsheet for the import handlers.
// - reads straight from PHP's upload temp file (nothing is written into the web root)
// - picks the reader from an allow-list of extensions instead of sniffing user input
// - reads cell values only (no formulas, styles or external references)

use PhpOffice\PhpSpreadsheet\IOFactory;

require_once __DIR__ . '/../vendor/autoload.php';

const IMPORT_MAX_BYTES = 10 * 1024 * 1024;
const IMPORT_READERS = ['xlsx' => 'Xlsx', 'xls' => 'Xls', 'csv' => 'Csv'];

/**
 * Returns [rows, null] on success or [null, user-facing error message] on failure.
 */
function readUploadedSpreadsheet($field = 'file')
{
    $file = isset($_FILES[$field]) ? $_FILES[$field] : null;
    if (!$file || $file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        return [null, "Please choose a spreadsheet to upload."];
    }
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!isset(IMPORT_READERS[$extension])) {
        return [null, "Only .xlsx, .xls and .csv files can be imported."];
    }
    if ($file['size'] > IMPORT_MAX_BYTES) {
        return [null, "The file is too large (maximum 10 MB)."];
    }

    try {
        $reader = IOFactory::createReader(IMPORT_READERS[$extension]);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($file['tmp_name']);
        return [$spreadsheet->getActiveSheet()->toArray(), null];
    } catch (\Throwable $e) {
        error_log('Spreadsheet import failed: ' . $e->getMessage());
        return [null, "The file could not be read as a spreadsheet."];
    }
}
