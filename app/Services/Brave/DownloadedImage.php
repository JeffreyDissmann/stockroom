<?php

declare(strict_types=1);

namespace App\Services\Brave;

class DownloadedImage
{
    public function __construct(
        public readonly string $contents,
        public readonly string $mime,
        public readonly string $filename,
    ) {}
}
