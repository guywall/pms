<?php

namespace App\Services;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;

class QrService
{
    /**
     * Returns raw PNG bytes for a QR code.
     */
    public function makePng(string $data, int $size = 220): string
    {
        return (new Builder(
            data: $data,
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: $size,
            margin: 2,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        ))->build()->getString();
    }
}
