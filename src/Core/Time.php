<?php
declare(strict_types=1);
namespace McRelish\Core;

final class Time
{
    /** "2026-10-28 14:30:00" (UTC from MySQL) to "2026-10-28T14:30:00Z"; null stays null. */
    public static function iso(?string $mysqlUtc): ?string
    {
        if ($mysqlUtc === null || $mysqlUtc === '') return null;
        return str_replace(' ', 'T', $mysqlUtc) . 'Z';
    }
}
