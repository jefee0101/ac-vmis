<?php

$input = __DIR__ . '/../docs/ac-vmis-data-dictionary.md';
$output = __DIR__ . '/../docs/ac-vmis-data-dictionary.html';

$lines = file($input, FILE_IGNORE_NEW_LINES);

if ($lines === false) {
    fwrite(STDERR, "Unable to read input file.\n");
    exit(1);
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function render_inline_code(string $text): string
{
    return preg_replace_callback('/`([^`]+)`/', function ($matches) {
        return '<code>' . h($matches[1]) . '</code>';
    }, h($text));
}

$html = [];
$html[] = '<!DOCTYPE html>';
$html[] = '<html lang="en">';
$html[] = '<head>';
$html[] = '  <meta charset="UTF-8">';
$html[] = '  <meta name="viewport" content="width=device-width, initial-scale=1.0">';
$html[] = '  <title>AC-VMIS Data Dictionary</title>';
$html[] = '  <style>';
$html[] = '    body { font-family: Arial, sans-serif; color: #111827; margin: 32px; line-height: 1.45; }';
$html[] = '    h1 { font-size: 24px; margin-bottom: 10px; }';
$html[] = '    h2 { font-size: 18px; margin-top: 28px; margin-bottom: 10px; color: #0f172a; }';
$html[] = '    p { margin: 0 0 16px; }';
$html[] = '    table { width: 100%; border-collapse: collapse; margin: 0 0 24px; table-layout: fixed; }';
$html[] = '    th, td { border: 1px solid #374151; padding: 8px 10px; vertical-align: top; text-align: left; word-wrap: break-word; }';
$html[] = '    th { background: #e5e7eb; font-weight: 700; }';
$html[] = '    code { font-family: "Courier New", monospace; font-size: 0.95em; background: #f3f4f6; padding: 1px 4px; }';
$html[] = '  </style>';
$html[] = '</head>';
$html[] = '<body>';

$i = 0;
$count = count($lines);

while ($i < $count) {
    $line = rtrim($lines[$i]);

    if ($line === '') {
        $i++;
        continue;
    }

    if (str_starts_with($line, '# ')) {
        $html[] = '<h1>' . render_inline_code(substr($line, 2)) . '</h1>';
        $i++;
        continue;
    }

    if (str_starts_with($line, '## ')) {
        $html[] = '<h2>' . render_inline_code(substr($line, 3)) . '</h2>';
        $i++;
        continue;
    }

    if (str_starts_with($line, '|')) {
        $rows = [];

        while ($i < $count && str_starts_with(rtrim($lines[$i]), '|')) {
            $current = trim($lines[$i]);

            if (!preg_match('/^\|\s*-+/', $current)) {
                $cells = array_map('trim', explode('|', trim($current, '|')));
                $rows[] = $cells;
            }

            $i++;
        }

        if ($rows !== []) {
            $html[] = '<table>';
            $html[] = '  <thead>';
            $html[] = '    <tr>';

            foreach ($rows[0] as $cell) {
                $html[] = '      <th>' . render_inline_code($cell) . '</th>';
            }

            $html[] = '    </tr>';
            $html[] = '  </thead>';

            if (count($rows) > 1) {
                $html[] = '  <tbody>';

                for ($r = 1; $r < count($rows); $r++) {
                    $html[] = '    <tr>';

                    foreach ($rows[$r] as $cell) {
                        $html[] = '      <td>' . render_inline_code($cell) . '</td>';
                    }

                    $html[] = '    </tr>';
                }

                $html[] = '  </tbody>';
            }

            $html[] = '</table>';
        }

        continue;
    }

    $paragraph = [$line];
    $i++;

    while ($i < $count && trim($lines[$i]) !== '' && !str_starts_with($lines[$i], '#') && !str_starts_with($lines[$i], '|')) {
        $paragraph[] = trim($lines[$i]);
        $i++;
    }

    $html[] = '<p>' . render_inline_code(implode(' ', $paragraph)) . '</p>';
}

$html[] = '</body>';
$html[] = '</html>';

if (file_put_contents($output, implode("\n", $html) . "\n") === false) {
    fwrite(STDERR, "Unable to write output file.\n");
    exit(1);
}

fwrite(STDOUT, "Created {$output}\n");
