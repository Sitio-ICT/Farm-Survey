<?php
// Builds the blank import templates in assets/ from filled-in form exports.
// Copies row 1 (the headers the importers read by column position) of every
// sheet into a fresh workbook, so no responses or author metadata end up in the repo.
//
// Usage: php scripts/make_templates.php <folder with the original .xlsx files>

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

$templates = [
    'Cluster Head Data.xlsx',
    'MAAN Inputs Inventory Form.xlsx',
    'MAAN Inputs distribution Form.xlsx',
];

$sourceDir = $argv[1] ?? null;
if ($sourceDir === null || !is_dir($sourceDir)) {
    fwrite(STDERR, "Usage: php scripts/make_templates.php <folder with the original .xlsx files>\n");
    exit(1);
}

foreach ($templates as $name) {
    $source = rtrim($sourceDir, '/\\') . DIRECTORY_SEPARATOR . $name;
    if (!is_file($source)) {
        fwrite(STDERR, "Missing: $source\n");
        exit(1);
    }

    // Build a fresh workbook with only the header row: the originals carry
    // hundreds of empty formatted rows and the authors' names in metadata.
    $original = IOFactory::load($source);
    $spreadsheet = new Spreadsheet();
    $spreadsheet->removeSheetByIndex(0);
    foreach ($original->getAllSheets() as $sourceSheet) {
        $lastColumn = $sourceSheet->getHighestDataColumn(1);
        $headers = $sourceSheet->rangeToArray("A1:{$lastColumn}1", null, false, false)[0];

        $sheet = new Worksheet($spreadsheet, $sourceSheet->getTitle());
        $spreadsheet->addSheet($sheet);
        $sheet->fromArray($headers, null, 'A1', true);
        $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true);
        $sheet->freezePane('A2');
        $columnCount = Coordinate::columnIndexFromString($lastColumn);
        for ($index = 1; $index <= $columnCount; $index++) {
            $column = Coordinate::stringFromColumnIndex($index);
            $width = $sourceSheet->getColumnDimension($column)->getWidth();
            if ($width > 0) {
                $sheet->getColumnDimension($column)->setWidth($width);
            }
        }
    }
    $spreadsheet->setActiveSheetIndex(0);
    $spreadsheet->getProperties()->setCreator('Farm Survey')->setLastModifiedBy('Farm Survey')->setTitle(pathinfo($name, PATHINFO_FILENAME));

    $target = __DIR__ . '/../assets/' . $name;
    IOFactory::createWriter($spreadsheet, 'Xlsx')->save($target);
    echo "Wrote assets/$name\n";
}
