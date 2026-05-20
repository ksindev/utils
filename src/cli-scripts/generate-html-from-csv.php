<?php

if (php_sapi_name() !== 'cli') {
    exit("This script must be run from CLI.\n");
}

// ===== Validate Arguments =====
if ($argc < 3) {
    echo "Usage: php generate-html-from-csv.php input.csv output.html\n";
    exit(1);
}

$inputCsv = $argv[1];
$outputHtml = $argv[2];

// ===== Validate Input File =====
if (!file_exists($inputCsv) || !is_readable($inputCsv)) {
    exit("Error: Input CSV file not found or not readable.\n");
}

// ===== Generate HTML =====
function csvToHtml(string $csvFilePath): string
{
    $fileName = pathinfo($csvFilePath, PATHINFO_FILENAME);
    $title = htmlspecialchars($fileName);

    $handle = fopen($csvFilePath, 'r');

    if ($handle === false) {
        throw new Exception("Unable to open CSV file.");
    }

    $html = "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>{$title}</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ccc; padding: 6px; text-align: left; }
        th { background-color: #f4f4f4; }
    </style>
</head>
<body>
    <h2>{$title}</h2>
    <table>";

    // Header
    if (($headers = fgetcsv($handle)) !== false) {
        $html .= "<thead><tr>";
        foreach ($headers as $header) {
            $html .= "<th>" . htmlspecialchars($header) . "</th>";
        }
        $html .= "</tr></thead><tbody>";
    }

    // Rows
    while (($row = fgetcsv($handle)) !== false) {
        $html .= "<tr>";
        foreach ($row as $cell) {
            $html .= "<td>" . htmlspecialchars($cell) . "</td>";
        }
        $html .= "</tr>";
    }

    $html .= "</tbody></table></body></html>";

    fclose($handle);

    return $html;
}

// ===== Execute =====
try {
    $htmlContent = csvToHtml($inputCsv);

    if (file_put_contents($outputHtml, $htmlContent) === false) {
        throw new Exception("Failed to write output file.");
    }

    echo "HTML file successfully generated: {$outputHtml}\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
