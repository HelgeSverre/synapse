<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Examples\AgenticTools;

use HelgeSverre\Synapse\Executor\CallableExecutor;

final class DateTimeTool
{
    public static function create(): CallableExecutor
    {
        return new CallableExecutor(
            name: 'datetime',
            description: 'Date and time operations: get current time, calculate difference between dates, or add an interval to a date.',
            handler: function (array $args): string {
                $action = $args['action'] ?? 'now';
                $timezone = $args['timezone'] ?? 'UTC';

                try {
                    $tz = new \DateTimeZone($timezone);
                } catch (\Throwable) {
                    return json_encode(['error' => "Invalid timezone: {$timezone}"], JSON_THROW_ON_ERROR);
                }

                return match ($action) {
                    'now' => self::now($tz),
                    'diff' => self::diff($args['date1'] ?? '', $args['date2'] ?? '', $tz),
                    'add' => self::add($args['date1'] ?? '', $args['interval'] ?? '', $tz),
                    default => json_encode(['error' => "Unknown action: {$action}"], JSON_THROW_ON_ERROR),
                };
            },
            parameters: [
                'type' => 'object',
                'properties' => [
                    'action' => [
                        'type' => 'string',
                        'enum' => ['now', 'diff', 'add'],
                        'description' => 'Action: now (current time), diff (difference between dates), add (add interval to date)',
                    ],
                    'timezone' => [
                        'type' => 'string',
                        'description' => 'Timezone for the operation (default: UTC), e.g. "America/New_York", "Europe/London"',
                    ],
                    'date1' => [
                        'type' => 'string',
                        'description' => 'First date in Y-m-d or Y-m-d H:i:s format (required for diff and add)',
                    ],
                    'date2' => [
                        'type' => 'string',
                        'description' => 'Second date in Y-m-d or Y-m-d H:i:s format (required for diff)',
                    ],
                    'interval' => [
                        'type' => 'string',
                        'description' => 'Interval to add in ISO 8601 format, e.g. "P1D" (1 day), "P2W" (2 weeks), "PT3H" (3 hours)',
                    ],
                ],
                'required' => ['action'],
            ],
        );
    }

    private static function now(\DateTimeZone $tz): string
    {
        $now = new \DateTimeImmutable('now', $tz);

        return json_encode([
            'datetime' => $now->format('Y-m-d H:i:s'),
            'date' => $now->format('Y-m-d'),
            'time' => $now->format('H:i:s'),
            'day_of_week' => $now->format('l'),
            'timezone' => $tz->getName(),
            'unix_timestamp' => $now->getTimestamp(),
        ], JSON_THROW_ON_ERROR);
    }

    private static function diff(string $date1, string $date2, \DateTimeZone $tz): string
    {
        if ($date1 === '' || $date2 === '') {
            return json_encode(['error' => 'Both date1 and date2 are required for diff action'], JSON_THROW_ON_ERROR);
        }

        try {
            $dt1 = new \DateTimeImmutable($date1, $tz);
            $dt2 = new \DateTimeImmutable($date2, $tz);
        } catch (\Throwable) {
            return json_encode(['error' => 'Invalid date format. Use Y-m-d or Y-m-d H:i:s'], JSON_THROW_ON_ERROR);
        }

        $diff = $dt1->diff($dt2);

        return json_encode([
            'date1' => $dt1->format('Y-m-d H:i:s'),
            'date2' => $dt2->format('Y-m-d H:i:s'),
            'difference' => [
                'years' => $diff->y,
                'months' => $diff->m,
                'days' => $diff->d,
                'hours' => $diff->h,
                'minutes' => $diff->i,
                'seconds' => $diff->s,
                'total_days' => $diff->days,
                'invert' => $diff->invert === 1,
            ],
        ], JSON_THROW_ON_ERROR);
    }

    private static function add(string $date1, string $interval, \DateTimeZone $tz): string
    {
        if ($date1 === '') {
            return json_encode(['error' => 'date1 is required for add action'], JSON_THROW_ON_ERROR);
        }

        if ($interval === '') {
            return json_encode(['error' => 'interval is required for add action'], JSON_THROW_ON_ERROR);
        }

        try {
            $dt = new \DateTimeImmutable($date1, $tz);
        } catch (\Throwable) {
            return json_encode(['error' => 'Invalid date format. Use Y-m-d or Y-m-d H:i:s'], JSON_THROW_ON_ERROR);
        }

        try {
            $intervalObj = new \DateInterval($interval);
        } catch (\Throwable) {
            return json_encode(['error' => "Invalid interval format: {$interval}. Use ISO 8601 format like P1D, P2W, PT3H"], JSON_THROW_ON_ERROR);
        }

        $result = $dt->add($intervalObj);

        return json_encode([
            'original_date' => $dt->format('Y-m-d H:i:s'),
            'interval' => $interval,
            'result_date' => $result->format('Y-m-d H:i:s'),
            'day_of_week' => $result->format('l'),
            'timezone' => $tz->getName(),
        ], JSON_THROW_ON_ERROR);
    }
}
