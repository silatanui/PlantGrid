<?php

namespace App\Support;

/**
 * Northridge plant schematic geometry (SVG viewBox 0 0 1000 640).
 */
class PlantMap
{
    public const VIEWBOX = '0 0 1000 640';

    /**
     * @return array<string, array{label: string, code: string, path: string, x: float, y: float}>
     */
    public static function zones(): array
    {
        return [
            'gate' => [
                'label' => 'Main Entrance',
                'code' => 'GATE',
                'path' => 'M40 40 H260 V150 H40 Z',
                'x' => 150.0,
                'y' => 95.0,
            ],
            'adm-bl' => [
                'label' => 'Administration Block',
                'code' => 'ADM-BL',
                'path' => 'M700 40 H960 V160 H700 Z',
                'x' => 830.0,
                'y' => 100.0,
            ],
            'ict-of' => [
                'label' => 'ICT Office',
                'code' => 'ICT-OF',
                'path' => 'M40 180 H250 V310 H40 Z',
                'x' => 145.0,
                'y' => 245.0,
            ],
            'srv-rm' => [
                'label' => 'Server Room',
                'code' => 'SRV-RM',
                'path' => 'M270 180 H470 V310 H270 Z',
                'x' => 370.0,
                'y' => 245.0,
            ],
            'qa-lab' => [
                'label' => 'QA Laboratory',
                'code' => 'QA-LAB',
                'path' => 'M700 180 H960 V310 H700 Z',
                'x' => 830.0,
                'y' => 245.0,
            ],
            'pkg-hl' => [
                'label' => 'Packaging Hall',
                'code' => 'PKG-HL',
                'path' => 'M40 340 H480 V520 H40 Z',
                'x' => 260.0,
                'y' => 430.0,
            ],
            'prc-hl' => [
                'label' => 'Processing Hall',
                'code' => 'PRC-HL',
                'path' => 'M500 340 H960 V520 H500 Z',
                'x' => 730.0,
                'y' => 430.0,
            ],
            'whs-of' => [
                'label' => 'Warehouse Office',
                'code' => 'WHS-OF',
                'path' => 'M40 540 H320 V620 H40 Z',
                'x' => 180.0,
                'y' => 580.0,
            ],
        ];
    }

    /**
     * @return array{label: string, code: string, path: string, x: float, y: float}|null
     */
    public static function zone(?string $key): ?array
    {
        if ($key === null || $key === '') {
            return null;
        }

        return self::zones()[$key] ?? null;
    }
}
