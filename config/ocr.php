<?php

return [

    'default_engine' => env('OCR_ENGINE', 'tesseract'),

    'tesseract' => [
        'binary' => env('TESSERACT_BINARY', 'tesseract'),
        'language' => env('TESSERACT_LANGUAGE', 'eng'),
        'timeout_seconds' => (int) env('TESSERACT_TIMEOUT', 30),
    ],

    'pdf' => [
        'renderer_binary' => env('OCR_PDF_RENDERER_BINARY', 'pdftoppm'),
        'density' => (int) env('OCR_PDF_DENSITY', 200),
        'max_pages' => (int) env('OCR_PDF_MAX_PAGES', 5),
        'timeout_seconds' => (int) env('OCR_PDF_TIMEOUT', 60),
    ],

    'queue' => env('OCR_QUEUE', 'default'),
];
