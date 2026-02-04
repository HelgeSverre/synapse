<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Examples\AgenticTools;

use HelgeSverre\Synapse\Executor\CallableExecutor;

final class WebSearchTool
{
    public static function create(): CallableExecutor
    {
        return new CallableExecutor(
            name: 'web_search',
            description: 'Search the web for information. Returns a list of search results with titles, URLs, and snippets.',
            handler: function (array $args): string {
                $query = $args['query'] ?? '';
                $limit = min(max((int) ($args['limit'] ?? 3), 1), 5);

                if ($query === '') {
                    return json_encode(['error' => 'Query is required'], JSON_THROW_ON_ERROR);
                }

                usleep(150_000);

                $hash = crc32(strtolower($query));
                $results = [];

                $domains = ['example.com', 'docs.example.org', 'wiki.example.net', 'blog.example.io', 'news.example.com'];
                $snippetPrefixes = [
                    'Learn everything about',
                    'A comprehensive guide to',
                    'The ultimate resource for',
                    'Discover the best practices for',
                    'An in-depth look at',
                ];

                for ($i = 0; $i < $limit; $i++) {
                    $resultHash = $hash + $i;
                    $domain = $domains[$resultHash % count($domains)];
                    $prefix = $snippetPrefixes[$resultHash % count($snippetPrefixes)];

                    $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $query));

                    $results[] = [
                        'title' => ucwords($query).' - Result '.($i + 1),
                        'url' => "https://{$domain}/{$slug}-".($resultHash % 1000),
                        'snippet' => "{$prefix} {$query}. This resource provides detailed information and examples.",
                    ];
                }

                return json_encode([
                    'query' => $query,
                    'results_count' => count($results),
                    'results' => $results,
                ], JSON_THROW_ON_ERROR);
            },
            parameters: [
                'type' => 'object',
                'properties' => [
                    'query' => [
                        'type' => 'string',
                        'description' => 'The search query',
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Maximum number of results to return (1-5, default: 3)',
                        'minimum' => 1,
                        'maximum' => 5,
                    ],
                ],
                'required' => ['query'],
            ],
        );
    }
}
