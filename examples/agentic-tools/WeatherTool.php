<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Examples\AgenticTools;

use HelgeSverre\Synapse\Executor\CallableExecutor;

final class WeatherTool
{
    public static function create(): CallableExecutor
    {
        return new CallableExecutor(
            name: 'get_weather',
            description: 'Get the current weather for a city. Returns temperature, conditions, humidity, and wind speed.',
            handler: function (array $args): string {
                $city = $args['city'] ?? '';
                $unit = $args['unit'] ?? 'celsius';

                usleep(100_000);

                $hash = crc32(strtolower($city));
                $baseTemp = ($hash % 35) + 5;
                $humidity = ($hash % 60) + 30;
                $windSpeed = ($hash % 25) + 5;

                $conditions = ['Sunny', 'Partly cloudy', 'Cloudy', 'Rainy', 'Thunderstorm', 'Snowy', 'Foggy', 'Clear'];
                $condition = $conditions[$hash % count($conditions)];

                $temperature = $unit === 'fahrenheit'
                    ? (int) round($baseTemp * 9 / 5 + 32)
                    : $baseTemp;

                return json_encode([
                    'city' => $city,
                    'temperature' => $temperature,
                    'unit' => $unit,
                    'conditions' => $condition,
                    'humidity' => $humidity,
                    'wind' => $windSpeed.' km/h',
                ], JSON_THROW_ON_ERROR);
            },
            parameters: [
                'type' => 'object',
                'properties' => [
                    'city' => [
                        'type' => 'string',
                        'description' => 'The name of the city to get weather for, e.g. "London" or "New York"',
                    ],
                    'unit' => [
                        'type' => 'string',
                        'enum' => ['celsius', 'fahrenheit'],
                        'description' => 'Temperature unit to use (default: celsius)',
                    ],
                ],
                'required' => ['city'],
            ],
        );
    }
}
