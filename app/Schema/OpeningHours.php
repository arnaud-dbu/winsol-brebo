<?php

namespace App\Schema;

/**
 * Vertaalt de menselijk geschreven openingstijden uit de `locations`-collectie
 * naar schema.org-specificaties. De opgeslagen vorm bevat dagreeksen
 * ("Di - Vr") en waarden die geen tijd zijn ("Op afspraak", "Gesloten").
 */
class OpeningHours
{
    private const DAYS = [
        'maandag' => 'Monday',    'ma' => 'Monday',
        'dinsdag' => 'Tuesday',   'di' => 'Tuesday',
        'woensdag' => 'Wednesday', 'wo' => 'Wednesday',
        'donderdag' => 'Thursday', 'do' => 'Thursday',
        'vrijdag' => 'Friday',    'vr' => 'Friday',
        'zaterdag' => 'Saturday', 'za' => 'Saturday',
        'zondag' => 'Sunday',     'zo' => 'Sunday',
    ];

    private const WEEK = [
        'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday',
    ];

    /**
     * @param  array<int, array{day?: string, time?: string}>  $rows
     * @return list<array<string, mixed>>
     */
    public static function specifications(array $rows): array
    {
        $specs = [];

        foreach ($rows as $row) {
            $days = self::days((string) ($row['day'] ?? ''));
            $hours = self::hours((string) ($row['time'] ?? ''));

            if ($days === [] || $hours === null) {
                continue;
            }

            $specs[] = [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => count($days) === 1 ? $days[0] : $days,
                'opens' => $hours[0],
                'closes' => $hours[1],
            ];
        }

        return $specs;
    }

    /**
     * @return list<string>
     */
    private static function days(string $value): array
    {
        $value = trim(mb_strtolower($value));

        if ($value === '') {
            return [];
        }

        if (! str_contains($value, '-')) {
            $day = self::DAYS[$value] ?? null;

            return $day === null ? [] : [$day];
        }

        [$from, $to] = array_map(trim(...), explode('-', $value, 2));

        $from = self::DAYS[$from] ?? null;
        $to = self::DAYS[$to] ?? null;

        if ($from === null || $to === null) {
            return [];
        }

        $start = (int) array_search($from, self::WEEK, true);
        $end = (int) array_search($to, self::WEEK, true);

        if ($start > $end) {
            return [];
        }

        return array_values(array_slice(self::WEEK, $start, $end - $start + 1));
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    private static function hours(string $value): ?array
    {
        $value = trim($value);

        if (mb_strtolower($value) === 'gesloten') {
            return ['00:00', '00:00'];
        }

        if (preg_match('/^(\d{1,2}:\d{2})\s*-\s*(\d{1,2}:\d{2})$/', $value, $matches)) {
            return [
                str_pad($matches[1], 5, '0', STR_PAD_LEFT),
                str_pad($matches[2], 5, '0', STR_PAD_LEFT),
            ];
        }

        return null;
    }
}
