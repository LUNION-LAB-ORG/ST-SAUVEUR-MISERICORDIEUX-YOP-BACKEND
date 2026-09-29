<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Http\Response;

/**
 * Générateur minimal de fichiers iCalendar (RFC 5545), heures locales Africa/Abidjan (TZID).
 */
class IcsCalendar
{
    public const TIMEZONE = 'Africa/Abidjan';

    private array $events = [];

    /**
     * @param array{uid:string,start:Carbon,end:?Carbon,summary:string,location?:?string,description?:?string,url?:?string} $event
     */
    public function addEvent(array $event): static
    {
        $this->events[] = $event;
        return $this;
    }

    public function render(): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Paroisse Saint Sauveur Misericordieux//FR',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            // Africa/Abidjan : UTC+0 toute l'année
            'BEGIN:VTIMEZONE',
            'TZID:' . self::TIMEZONE,
            'BEGIN:STANDARD',
            'DTSTART:19700101T000000',
            'TZOFFSETFROM:+0000',
            'TZOFFSETTO:+0000',
            'TZNAME:GMT',
            'END:STANDARD',
            'END:VTIMEZONE',
        ];

        $stamp = Carbon::now('UTC')->format('Ymd\THis\Z');

        foreach ($this->events as $event) {
            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:' . $event['uid'];
            $lines[] = 'DTSTAMP:' . $stamp;
            $lines[] = 'DTSTART;TZID=' . self::TIMEZONE . ':' . $event['start']->format('Ymd\THis');
            if (!empty($event['end'])) {
                $lines[] = 'DTEND;TZID=' . self::TIMEZONE . ':' . $event['end']->format('Ymd\THis');
            }
            $lines[] = 'SUMMARY:' . self::escape($event['summary']);
            foreach (['location' => 'LOCATION', 'description' => 'DESCRIPTION'] as $key => $prop) {
                if (!empty($event[$key])) {
                    $lines[] = $prop . ':' . self::escape($event[$key]);
                }
            }
            if (!empty($event['url'])) {
                $lines[] = 'URL:' . $event['url'];
            }
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map([self::class, 'fold'], $lines)) . "\r\n";
    }

    public function download(string $filename): Response
    {
        return response($this->render(), 200, [
            'Content-Type'        => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private static function escape(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        return str_replace(['\\', ';', ',', "\n"], ['\\\\', '\;', '\,', '\n'], $text);
    }

    /** Pliage des lignes à 75 octets sans couper un caractère UTF-8. */
    private static function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $out = '';
        $current = '';
        foreach (mb_str_split($line) as $char) {
            $limit = $out === '' ? 75 : 74;
            if (strlen($current . $char) > $limit) {
                $out .= ($out === '' ? '' : "\r\n ") . $current;
                $current = '';
            }
            $current .= $char;
        }

        return $out . "\r\n " . $current;
    }
}
