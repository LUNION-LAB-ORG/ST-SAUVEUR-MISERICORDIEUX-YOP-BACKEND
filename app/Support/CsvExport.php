<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export CSV pour Excel (version française) : UTF-8 avec BOM, séparateur « ; ».
 */
class CsvExport
{
    /**
     * @param string[] $headers En-têtes (en français)
     * @param iterable<array> $rows
     */
    public static function download(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers, ';');

            foreach ($rows as $row) {
                fputcsv($out, array_map(fn ($v) => $v === null ? '' : (string) $v, $row), ';');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Date au format français (JJ/MM/AAAA [HH:MM]). */
    public static function date($value, bool $withTime = false): string
    {
        if (!$value) {
            return '';
        }

        $date = $value instanceof \DateTimeInterface ? $value : \Carbon\Carbon::parse($value);

        return $date->format($withTime ? 'd/m/Y H:i' : 'd/m/Y');
    }

    /** Libellés français des statuts de paiement. */
    public static function paymentStatus(?string $status): string
    {
        return match ($status) {
            'succeeded', 'paid' => 'Payé',
            'pending'           => 'En attente',
            'to_pay'            => 'À régler au secrétariat',
            'failed'            => 'Échoué',
            'free'              => 'Gratuit',
            default             => (string) $status,
        };
    }
}
