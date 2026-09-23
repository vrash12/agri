<?php

namespace App\Support;

use InvalidArgumentException;

final class FarmerIdentifier
{
    public static function format(int $id): string
    {
        if ($id < 1) {
            throw new InvalidArgumentException('A saved farmer ID is required.');
        }

        return 'AGRI-F-'.str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Resolve a complete displayed ID to the existing indexed database key.
     * Keep printed legacy cards searchable without changing their QR tokens.
     */
    public static function parse(string $identifier): ?int
    {
        if (preg_match('/^(?:AGRI-F|PAIS-FRM)-([0-9]+)$/iD', trim($identifier), $matches) !== 1) {
            return null;
        }

        $digits = ltrim($matches[1], '0');
        if ($digits === '') {
            return null;
        }

        $id = filter_var($digits, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $id === false ? null : $id;
    }
}
