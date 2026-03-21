<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

class SecureUploadService
{
    public function storePublic(UploadedFile $file, string $directory, string $profile): string
    {
        $this->assertSecure($file, $profile);
        return $file->store($directory, 'public');
    }

    public function assertSecure(UploadedFile $file, string $profile): void
    {
        $config = (array) config("upload_security.profiles.{$profile}", []);
        if (empty($config)) {
            throw ValidationException::withMessages([
                'file' => "Upload profile '{$profile}' is not configured.",
            ]);
        }

        $maxKb = (int) ($config['max_kb'] ?? 0);
        if ($maxKb > 0 && $file->getSize() > ($maxKb * 1024)) {
            throw ValidationException::withMessages([
                'file' => "File exceeds the {$maxKb}KB limit.",
            ]);
        }

        $mime = strtolower((string) $file->getMimeType());
        $allowedMimes = collect((array) ($config['mime_types'] ?? []))
            ->map(fn ($m) => strtolower((string) $m))
            ->all();

        if (!in_array($mime, $allowedMimes, true)) {
            throw ValidationException::withMessages([
                'file' => 'Unsupported or unsafe file type.',
            ]);
        }

        $this->scanForMalware($file);
    }

    private function scanForMalware(UploadedFile $file): void
    {
        if (!(bool) config('upload_security.antivirus.enabled', false)) {
            return;
        }

        $binary = (string) config('upload_security.antivirus.binary', 'clamscan');
        $process = new Process([$binary, '--no-summary', (string) $file->getRealPath()]);
        $process->setTimeout((int) config('upload_security.antivirus.timeout_seconds', 20));
        $process->run();

        if ($process->isSuccessful()) {
            return;
        }

        $exitCode = (int) $process->getExitCode();
        if ($exitCode === 1) {
            throw ValidationException::withMessages([
                'file' => 'Potential malware detected in uploaded file.',
            ]);
        }

        $failOpen = (bool) config('upload_security.antivirus.fail_open', true);
        if (!$failOpen) {
            throw ValidationException::withMessages([
                'file' => 'Unable to complete antivirus scan. Please try again.',
            ]);
        }

        Log::warning('Antivirus scan failed; upload allowed due to fail-open policy.', [
            'exit_code' => $exitCode,
        ]);
    }
}
