<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

class Csv
{
    public static function download(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);

            foreach ($rows as $row) {
                fputcsv($handle, array_map([self::class, 'cell'], $row));
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public static function cell(mixed $value): string
    {
        if (is_array($value)) {
            $value = $value['text'] ?? '';
        }

        $text = (string) ($value ?? '');

        if (preg_match('/^[=+\-@]/', $text) === 1) {
            return "'".$text;
        }

        return $text;
    }
}
