<?php

namespace App\Users\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

class AntivirusScanner
{
    public function scan(UploadedFile $file): void
    {
        $binary = (string) config('services.antivirus.binary');

        if ($binary === '') {
            return;
        }

        $process = new Process([$binary, '--no-summary', $file->getRealPath()]);
        $process->setTimeout((int) config('services.antivirus.timeout', 30));
        $process->run();

        if (! $process->isSuccessful()) {
            throw ValidationException::withMessages([
                'file' => 'Documentul nu a trecut scanarea antivirus.',
            ]);
        }
    }
}
