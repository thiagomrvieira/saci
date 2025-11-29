<?php

namespace ThiagoVieira\Saci\Collectors;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Config;

class DatabaseCollector extends BaseCollector
{
    /**
     * Threshold for slow query detection (in milliseconds)
     */
    protected const SLOW_QUERY_THRESHOLD = 100;

    /**
     * Minimum occurrences to consider as N+1 pattern
     */
    protected const N_PLUS_ONE_THRESHOLD = 3;

    /**
     * Maximum examples to keep for N+1 patterns
     */
    protected const N_PLUS_ONE_MAX_EXAMPLES = 3;

    /**
     * Maximum length for long string bindings
     */
    protected const MAX_BINDING_LENGTH = 100;

    protected array $queries = [];
    protected array $connections = [];
    protected float $totalTime = 0;
    protected ?string $listenerHandle = null;

    public function getName(): string
    {
        return 'database';
    }

    public function getLabel(): string
    {
        return 'Database';
    }

    protected function doStart(): void
    {
        $this->queries = [];
        $this->connections = [];
        $this->totalTime = 0;

        // Listen to QueryExecuted events from all database connections
        $this->listenerHandle = Event::listen(QueryExecuted::class, function (QueryExecuted $query) {
            $this->recordQuery($query);
        });
    }

    protected function doCollect(): void
    {
        $this->data = [
            'queries' => $this->queries,
            'total_queries' => count($this->queries),
            'total_time' => round($this->totalTime, 2),
            'connections' => array_unique($this->connections),
            'slow_queries' => $this->getSlowQueries(),
            'duplicate_queries' => $this->detectDuplicates(),
            'possible_n_plus_one' => $this->detectNPlusOne(),
        ];
    }

    protected function doReset(): void
    {
        $this->queries = [];
        $this->connections = [];
        $this->totalTime = 0;

        if ($this->listenerHandle) {
            Event::forget(QueryExecuted::class);
            $this->listenerHandle = null;
        }
    }

    /**
     * Record a query from QueryExecuted event
     */
    protected function recordQuery(QueryExecuted $event): void
    {
        $sql = $event->sql;
        $bindings = $event->bindings;
        $time = $event->time;
        $connection = $event->connectionName;

        // Get stack trace to identify where the query was called
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 20);
        $caller = $this->findRelevantCaller($backtrace);

        // Get database driver type and database name
        $connectionInfo = $this->getConnectionInfo($connection);

        $this->queries[] = [
            'sql' => $this->sqlToString($sql),
            'bindings' => $this->formatBindings($bindings),
            'time' => round($time, 2),
            'connection' => $connection,
            'driver' => $connectionInfo['driver'],
            'database' => $connectionInfo['database'],
            'caller' => $caller,
            'is_slow' => $time > self::SLOW_QUERY_THRESHOLD,
        ];

        $this->connections[] = $connection;
        $this->totalTime += $time;
    }

    /**
     * Convert SQL to string (handles Query\Expression objects)
     */
    protected function sqlToString($sql): string
    {
        if ($sql instanceof \Illuminate\Database\Query\Expression) {
            // Access the protected $value property via reflection
            $reflection = new \ReflectionClass($sql);
            $property = $reflection->getProperty('value');
            $property->setAccessible(true);
            return (string) $property->getValue($sql);
        }
        return (string) $sql;
    }

    /**
     * Format bindings for display
     */
    protected function formatBindings(array $bindings): array
    {
        return array_map(function ($binding) {
            // Handle Query\Expression objects in bindings
            if ($binding instanceof \Illuminate\Database\Query\Expression) {
                return $this->sqlToString($binding);
            }
            if ($binding instanceof \DateTime) {
                return $binding->format('Y-m-d H:i:s');
            }
            if (is_bool($binding)) {
                return $binding ? 'true' : 'false';
            }
            if (is_null($binding)) {
                return 'NULL';
            }
            if (is_string($binding) && strlen($binding) > self::MAX_BINDING_LENGTH) {
                return substr($binding, 0, self::MAX_BINDING_LENGTH) . '...';
            }
            return $binding;
        }, $bindings);
    }

    /**
     * Find the relevant caller from stack trace (skip framework internals)
     */
    protected function findRelevantCaller(array $backtrace): ?array
    {
        $skipPatterns = [
            '/vendor\/laravel\/framework/',
            '/vendor\/illuminate/',
            '/DatabaseCollector\.php/',
        ];

        foreach ($backtrace as $trace) {
            $file = $trace['file'] ?? '';

            // Skip framework files
            $shouldSkip = false;
            foreach ($skipPatterns as $pattern) {
                if (preg_match($pattern, $file)) {
                    $shouldSkip = true;
                    break;
                }
            }

            if (!$shouldSkip && $file) {
                return [
                    'file' => $file,
                    'line' => $trace['line'] ?? 0,
                    'function' => $trace['function'] ?? null,
                    'class' => $trace['class'] ?? null,
                ];
            }
        }

        return null;
    }

    /**
     * Get queries that took longer than threshold
     */
    protected function getSlowQueries(?float $threshold = null): array
    {
        $threshold = $threshold ?? self::SLOW_QUERY_THRESHOLD;

        return array_values(array_filter($this->queries, function ($query) use ($threshold) {
            return $query['time'] > $threshold;
        }));
    }

    /**
     * Detect duplicate queries (same SQL executed multiple times)
     */
    protected function detectDuplicates(): array
    {
        $grouped = [];

        foreach ($this->queries as $query) {
            $key = $query['sql'];
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'sql' => $query['sql'],
                    'count' => 0,
                    'total_time' => 0,
                ];
            }
            $grouped[$key]['count']++;
            $grouped[$key]['total_time'] += $query['time'];
        }

        // Filter only duplicates (count > 1)
        $duplicates = array_filter($grouped, function ($item) {
            return $item['count'] > 1;
        });

        // Sort by count (most duplicated first)
        usort($duplicates, function ($a, $b) {
            return $b['count'] <=> $a['count'];
        });

        return array_values($duplicates);
    }

    /**
     * Detect possible N+1 query patterns
     *
     * The N+1 query problem occurs when an application executes one query
     * to fetch a list of records, then executes N additional queries to fetch
     * related data for each record (1 + N = N+1 queries).
     *
     * This method looks for patterns like:
     * - SELECT * FROM users WHERE id = 1
     * - SELECT * FROM users WHERE id = 2
     * - SELECT * FROM users WHERE id = 3
     *
     * @return array List of detected N+1 patterns with examples and statistics
     */
    protected function detectNPlusOne(): array
    {
        $patterns = [];

        foreach ($this->queries as $query) {
            // Normalize query by replacing numeric bindings with placeholders
            $normalized = $this->normalizeQuery($query['sql'], $query['bindings']);

            if (!isset($patterns[$normalized])) {
                $patterns[$normalized] = [
                    'pattern' => $normalized,
                    'original_sql' => $query['sql'],
                    'count' => 0,
                    'total_time' => 0,
                    'examples' => [],
                ];
            }

            $patterns[$normalized]['count']++;
            $patterns[$normalized]['total_time'] += $query['time'];

            // Keep first N examples for display
            if (count($patterns[$normalized]['examples']) < self::N_PLUS_ONE_MAX_EXAMPLES) {
                $patterns[$normalized]['examples'][] = [
                    'sql' => $query['sql'],
                    'bindings' => $query['bindings'],
                ];
            }
        }

        // Filter patterns that appear multiple times (likely N+1)
        $nPlusOne = array_filter($patterns, function ($pattern) {
            return $pattern['count'] >= self::N_PLUS_ONE_THRESHOLD;
        });

        // Sort by count (most repeated first)
        usort($nPlusOne, function ($a, $b) {
            return $b['count'] <=> $a['count'];
        });

        return array_values($nPlusOne);
    }

    /**
     * Normalize query by replacing specific values with placeholders
     */
    protected function normalizeQuery(string $sql, array $bindings): string
    {
        // Replace numeric values in WHERE clauses
        $normalized = preg_replace('/= \d+/', '= ?', $sql);
        $normalized = preg_replace('/IN \([0-9, ]+\)/', 'IN (?)', $normalized);

        return $normalized;
    }

    /**
     * Get the driver type and database name for a connection
     */
    protected function getConnectionInfo(string $connectionName): array
    {
        try {
            $connection = DB::connection($connectionName);
            $driver = $connection->getDriverName();

            // Map driver names to friendly names
            $driverMap = [
                'mysql' => 'MySQL',
                'pgsql' => 'PostgreSQL',
                'sqlite' => 'SQLite',
                'sqlsrv' => 'SQL Server',
            ];

            // Get database name
            $database = $connection->getDatabaseName();

            return [
                'driver' => $driverMap[$driver] ?? ucfirst($driver),
                'database' => $database ?: $connectionName,
            ];
        } catch (\Exception $e) {
            return [
                'driver' => 'Unknown',
                'database' => $connectionName,
            ];
        }
    }
}

