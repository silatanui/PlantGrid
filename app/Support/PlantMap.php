<?php

namespace App\Support;

/**
 * Northridge campus schematic (SVG viewBox 0 0 1200 800).
 */
class PlantMap
{
    public const VIEWBOX = '0 0 1200 800';

    /**
     * @return array<string, array{
     *     label: string,
     *     code: string,
     *     path: string,
     *     x: float,
     *     y: float,
     *     label_x: float,
     *     label_y: float,
     *     slots: list<array{0: float, 1: float}>
     * }>
     */
    public static function zones(): array
    {
        return [
            'gate' => [
                'label' => 'Main Entrance',
                'code' => 'GATE',
                'path' => 'M430 70 H620 V190 H430 Z',
                'x' => 525.0,
                'y' => 145.0,
                'label_x' => 525.0,
                'label_y' => 92.0,
                'slots' => [
                    [470, 130], [520, 130], [570, 130],
                    [470, 165], [545, 165],
                ],
            ],
            'adm-bl' => [
                'label' => 'Administration',
                'code' => 'ADM-BL',
                'path' => 'M780 60 H1120 V210 H780 Z',
                'x' => 950.0,
                'y' => 145.0,
                'label_x' => 950.0,
                'label_y' => 82.0,
                'slots' => [
                    [820, 115], [880, 115], [940, 115], [1000, 115], [1060, 115],
                    [820, 165], [900, 165], [980, 165], [1060, 165],
                ],
            ],
            'ict-of' => [
                'label' => 'ICT Office',
                'code' => 'ICT-OF',
                'path' => 'M70 250 H300 V410 H70 Z',
                'x' => 185.0,
                'y' => 340.0,
                'label_x' => 185.0,
                'label_y' => 272.0,
                'slots' => [
                    [110, 310], [170, 310], [230, 310],
                    [110, 360], [170, 360], [230, 360],
                    [270, 335],
                ],
            ],
            'srv-rm' => [
                'label' => 'Server Room',
                'code' => 'SRV-RM',
                'path' => 'M320 250 H520 V410 H320 Z',
                'x' => 420.0,
                'y' => 340.0,
                'label_x' => 420.0,
                'label_y' => 272.0,
                'slots' => [
                    [360, 305], [420, 305], [480, 305],
                    [360, 355], [420, 355], [480, 355],
                ],
            ],
            'qa-lab' => [
                'label' => 'QA Laboratory',
                'code' => 'QA-LAB',
                'path' => 'M860 250 H1120 V410 H860 Z',
                'x' => 990.0,
                'y' => 340.0,
                'label_x' => 990.0,
                'label_y' => 272.0,
                'slots' => [
                    [900, 305], [960, 305], [1020, 305], [1080, 305],
                    [900, 360], [980, 360], [1060, 360],
                ],
            ],
            'pkg-hl' => [
                'label' => 'Packaging Hall',
                'code' => 'PKG-HL',
                'path' => 'M70 470 H560 V700 H70 Z',
                'x' => 315.0,
                'y' => 600.0,
                'label_x' => 315.0,
                'label_y' => 495.0,
                'slots' => [
                    [130, 545], [220, 545], [310, 545], [400, 545], [490, 545],
                    [130, 610], [220, 610], [310, 610], [400, 610], [490, 610],
                    [175, 660], [315, 660], [455, 660],
                ],
            ],
            'prc-hl' => [
                'label' => 'Processing Hall',
                'code' => 'PRC-HL',
                'path' => 'M620 470 H1120 V700 H620 Z',
                'x' => 870.0,
                'y' => 600.0,
                'label_x' => 870.0,
                'label_y' => 495.0,
                'slots' => [
                    [680, 545], [780, 545], [880, 545], [980, 545], [1060, 545],
                    [680, 610], [780, 610], [880, 610], [980, 610], [1060, 610],
                    [730, 660], [870, 660], [1010, 660],
                ],
            ],
            'whs-of' => [
                'label' => 'Warehouse Office',
                'code' => 'WHS-OF',
                'path' => 'M70 720 H360 V780 H70 Z',
                'x' => 215.0,
                'y' => 755.0,
                'label_x' => 215.0,
                'label_y' => 738.0,
                'slots' => [
                    [120, 755], [180, 755], [240, 755], [300, 755],
                ],
            ],
        ];
    }

    /**
     * @return array{label: string, code: string, path: string, x: float, y: float, label_x: float, label_y: float, slots: list<array{0: float, 1: float}>}|null
     */
    public static function zone(?string $key): ?array
    {
        if ($key === null || $key === '') {
            return null;
        }

        return self::zones()[$key] ?? null;
    }

    /**
     * @return list<array{0: float, 1: float}>
     */
    public static function pinSlots(string $zoneKey): array
    {
        $zone = self::zone($zoneKey);

        return $zone['slots'] ?? [[500.0, 400.0]];
    }
}
