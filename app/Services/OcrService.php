<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

class OcrService
{
    public function extractText(string $absolutePath): array
    {
        if (!is_file($absolutePath)) {
            throw new RuntimeException('OCR source file was not found.');
        }

        $extension = strtolower((string) pathinfo($absolutePath, PATHINFO_EXTENSION));
        if ($extension === 'pdf') {
            return $this->extractFromPdf($absolutePath);
        }

        return $this->extractFromImage($absolutePath);
    }

    private function extractFromImage(string $absolutePath): array
    {
        $binary = $this->resolveBinary(
            (string) config('ocr.tesseract.binary', 'tesseract'),
            'tesseract'
        );
        $language = (string) config('ocr.tesseract.language', 'eng');
        $timeout = (int) config('ocr.tesseract.timeout_seconds', 30);

        $rawText = $this->runTesseractText($binary, $language, $timeout, $absolutePath);
        $meanConfidence = $this->runTesseractConfidence($binary, $language, $timeout, $absolutePath);

        return [
            'engine' => (string) config('ocr.default_engine', 'tesseract'),
            'engine_version' => $this->detectVersion($binary, $timeout),
            'raw_text' => $rawText,
            'mean_confidence' => $meanConfidence,
        ];
    }

    private function extractFromPdf(string $absolutePath): array
    {
        $rendererBinary = $this->resolveBinary(
            (string) config('ocr.pdf.renderer_binary', 'pdftoppm'),
            'pdftoppm'
        );
        $pdfTimeout = (int) config('ocr.pdf.timeout_seconds', 60);
        $density = max(72, (int) config('ocr.pdf.density', 200));
        $maxPages = max(1, (int) config('ocr.pdf.max_pages', 5));
        $binary = $this->resolveBinary(
            (string) config('ocr.tesseract.binary', 'tesseract'),
            'tesseract'
        );
        $language = (string) config('ocr.tesseract.language', 'eng');
        $timeout = (int) config('ocr.tesseract.timeout_seconds', 30);

        $tempDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'ac-vmis-ocr-' . Str::uuid();
        if (!mkdir($tempDir, 0777, true) && !is_dir($tempDir)) {
            throw new RuntimeException('Unable to prepare temporary OCR workspace for PDF processing.');
        }

        try {
            $prefix = $tempDir . DIRECTORY_SEPARATOR . 'page';
            $renderProcess = new Process([
                $rendererBinary,
                '-png',
                '-r',
                (string) $density,
                '-f',
                '1',
                '-l',
                (string) $maxPages,
                $absolutePath,
                $prefix,
            ]);
            $renderProcess->setTimeout($pdfTimeout);
            $renderProcess->run();

            if (!$renderProcess->isSuccessful()) {
                $error = trim($renderProcess->getErrorOutput()) ?: trim($renderProcess->getOutput()) ?: 'PDF rendering failed.';
                throw new RuntimeException($error);
            }

            $pageImages = glob($prefix . '-*.png') ?: [];
            sort($pageImages);

            if ($pageImages === []) {
                throw new RuntimeException('No page images were produced from the uploaded PDF.');
            }

            $texts = [];
            $confidences = [];

            foreach ($pageImages as $pageImage) {
                if (!is_string($pageImage) || !is_file($pageImage)) {
                    continue;
                }

                $pageText = $this->runTesseractText($binary, $language, $timeout, $pageImage);
                if ($pageText !== '') {
                    $texts[] = $pageText;
                }

                $pageConfidence = $this->runTesseractConfidence($binary, $language, $timeout, $pageImage);
                if ($pageConfidence !== null) {
                    $confidences[] = $pageConfidence;
                }
            }

            return [
                'engine' => (string) config('ocr.default_engine', 'tesseract'),
                'engine_version' => $this->detectVersion($binary, $timeout),
                'raw_text' => trim(implode("\n\n", $texts)),
                'mean_confidence' => $confidences !== []
                    ? round(array_sum($confidences) / count($confidences), 2)
                    : null,
            ];
        } finally {
            $this->cleanupDirectory($tempDir);
        }
    }

    private function runTesseractText(string $binary, string $language, int $timeout, string $absolutePath): string
    {
        $process = new Process([$binary, $absolutePath, 'stdout', '-l', $language]);
        $process->setTimeout($timeout);
        $process->run();

        if (!$process->isSuccessful()) {
            $error = trim($process->getErrorOutput()) ?: trim($process->getOutput()) ?: 'OCR execution failed.';
            throw new RuntimeException($error);
        }

        return trim($process->getOutput());
    }

    private function runTesseractConfidence(string $binary, string $language, int $timeout, string $absolutePath): ?float
    {
        $process = new Process([$binary, $absolutePath, 'stdout', '-l', $language, 'tsv']);
        $process->setTimeout($timeout);
        $process->run();

        if (!$process->isSuccessful()) {
            return null;
        }

        $rows = preg_split('/\R+/', trim($process->getOutput())) ?: [];
        $confidences = [];

        foreach (array_slice($rows, 1) as $row) {
            $columns = preg_split("/\t+/", trim((string) $row)) ?: [];
            $confidence = Arr::get($columns, 10);
            if (!is_string($confidence) || $confidence === '') {
                continue;
            }

            $value = (float) $confidence;
            if ($value >= 0) {
                $confidences[] = $value;
            }
        }

        return $confidences !== []
            ? round(array_sum($confidences) / count($confidences), 2)
            : null;
    }

    private function detectVersion(string $binary, int $timeout): ?string
    {
        $process = new Process([$binary, '--version']);
        $process->setTimeout($timeout);
        $process->run();

        if (!$process->isSuccessful()) {
            return null;
        }

        $firstLine = strtok($process->getOutput(), "\n");

        return $firstLine !== false ? trim($firstLine) : null;
    }

    private function cleanupDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $entries = scandir($directory) ?: [];
        foreach ($entries as $entry) {
            if (in_array($entry, ['.', '..'], true)) {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($path)) {
                $this->cleanupDirectory($path);
                continue;
            }

            @unlink($path);
        }

        @rmdir($directory);
    }

    private function resolveBinary(string $configuredBinary, string $fallbackBinary): string
    {
        $configuredBinary = trim($configuredBinary);
        $fallbackBinary = trim($fallbackBinary);

        if ($configuredBinary !== '' && $this->binaryAvailable($configuredBinary)) {
            return $configuredBinary;
        }

        if ($fallbackBinary !== '' && $fallbackBinary !== $configuredBinary && $this->binaryAvailable($fallbackBinary)) {
            return $fallbackBinary;
        }

        $preferred = $configuredBinary !== '' ? $configuredBinary : $fallbackBinary;

        throw new RuntimeException("OCR binary [{$preferred}] is not available on this server.");
    }

    private function binaryAvailable(string $binary): bool
    {
        if ($binary === '') {
            return false;
        }

        if (str_contains($binary, DIRECTORY_SEPARATOR)) {
            return is_file($binary) && is_executable($binary);
        }

        $process = new Process(['sh', '-lc', 'command -v ' . escapeshellarg($binary)]);
        $process->setTimeout(5);
        $process->run();

        return $process->isSuccessful() && trim($process->getOutput()) !== '';
    }
}
