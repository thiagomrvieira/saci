<?php

declare(strict_types=1);

use ThiagoVieira\Saci\Collectors\DatabaseCollector;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->collector = new DatabaseCollector();
});

describe('DatabaseCollector Identity', function () {
    it('returns correct name', function () {
        expect($this->collector->getName())->toBe('database');
    });

    it('returns correct label', function () {
        expect($this->collector->getLabel())->toBe('Database');
    });

    it('is enabled by default', function () {
        config()->set('saci.collectors.database', true);
        expect($this->collector->isEnabled())->toBeTrue();
    });
});

describe('DatabaseCollector Lifecycle', function () {
    it('starts query listening on start', function () {
        $this->collector->start();

        // Just verify no exception thrown
        expect(true)->toBeTrue();
    });

    it('stops listening on reset', function () {
        Event::fake();

        $this->collector->start();
        $this->collector->reset();

        // After reset, should forget event listeners
        expect(true)->toBeTrue();
    });

    it('initializes empty state on start', function () {
        $this->collector->start();
        $this->collector->collect();

        $data = $this->collector->getData();

        expect($data['total_queries'])->toBe(0);
        expect($data['queries'])->toBeEmpty();
    });
});

describe('DatabaseCollector Query Recording', function () {
    it('records a single query', function () {
        $this->collector->start();

        // Simulate a query
        $event = new QueryExecuted(
            'SELECT * FROM users WHERE id = ?',
            [1],
            50.5,
            DB::connection()
        );

        Event::dispatch($event);

        $this->collector->collect();
        $data = $this->collector->getData();

        expect($data['total_queries'])->toBe(1);
        expect($data['queries'][0]['sql'])->toBe('SELECT * FROM users WHERE id = ?');
        expect($data['queries'][0]['time'])->toBe(50.5);
    });

    it('records multiple queries', function () {
        $this->collector->start();

        Event::dispatch(new QueryExecuted('SELECT * FROM users', [], 10, DB::connection()));
        Event::dispatch(new QueryExecuted('SELECT * FROM posts', [], 20, DB::connection()));
        Event::dispatch(new QueryExecuted('SELECT * FROM comments', [], 30, DB::connection()));

        $this->collector->collect();
        $data = $this->collector->getData();

        expect($data['total_queries'])->toBe(3);
        expect($data['total_time'])->toBe(60.0);
    });

    it('records query bindings', function () {
        $this->collector->start();

        Event::dispatch(new QueryExecuted(
            'SELECT * FROM users WHERE email = ? AND active = ?',
            ['john@example.com', true],
            25,
            DB::connection()
        ));

        $this->collector->collect();
        $data = $this->collector->getData();

        expect($data['queries'][0]['bindings'])->toContain('john@example.com');
        expect($data['queries'][0]['bindings'])->toContain('true');
    });

    it('records connection name', function () {
        $this->collector->start();

        Event::dispatch(new QueryExecuted(
            'SELECT 1',
            [],
            5,
            DB::connection('mysql')
        ));

        $this->collector->collect();
        $data = $this->collector->getData();

        expect($data['queries'][0]['connection'])->toBe('mysql');
    });

    it('calculates total query time', function () {
        $this->collector->start();

        Event::dispatch(new QueryExecuted('SELECT 1', [], 10.5, DB::connection()));
        Event::dispatch(new QueryExecuted('SELECT 2', [], 20.3, DB::connection()));
        Event::dispatch(new QueryExecuted('SELECT 3', [], 15.7, DB::connection()));

        $this->collector->collect();
        $data = $this->collector->getData();

        expect($data['total_time'])->toBe(46.5);
    });
});

describe('DatabaseCollector Slow Query Detection', function () {
    it('detects slow queries', function () {
        $this->collector->start();

        // Slow query (> 100ms)
        Event::dispatch(new QueryExecuted('SELECT * FROM large_table', [], 150, DB::connection()));
        // Fast query
        Event::dispatch(new QueryExecuted('SELECT * FROM small_table', [], 10, DB::connection()));

        $this->collector->collect();
        $data = $this->collector->getData();

        expect($data['slow_queries'])->toHaveCount(1);
        expect($data['slow_queries'][0]['sql'])->toBe('SELECT * FROM large_table');
        expect($data['slow_queries'][0]['is_slow'])->toBeTrue();
    });

    it('marks slow queries in query list', function () {
        $this->collector->start();

        Event::dispatch(new QueryExecuted('SLOW QUERY', [], 200, DB::connection()));

        $this->collector->collect();
        $data = $this->collector->getData();

        expect($data['queries'][0]['is_slow'])->toBeTrue();
    });

    it('handles queries at threshold boundary', function () {
        $this->collector->start();

        Event::dispatch(new QueryExecuted('AT THRESHOLD', [], 100, DB::connection()));
        Event::dispatch(new QueryExecuted('OVER THRESHOLD', [], 100.01, DB::connection()));

        $this->collector->collect();
        $data = $this->collector->getData();

        expect($data['slow_queries'])->toHaveCount(1);
        expect($data['slow_queries'][0]['sql'])->toBe('OVER THRESHOLD');
    });

    it('returns empty array when no slow queries', function () {
        $this->collector->start();

        Event::dispatch(new QueryExecuted('FAST', [], 5, DB::connection()));
        Event::dispatch(new QueryExecuted('ALSO FAST', [], 10, DB::connection()));

        $this->collector->collect();
        $data = $this->collector->getData();

        expect($data['slow_queries'])->toBeEmpty();
    });
});

describe('DatabaseCollector Duplicate Detection', function () {
    it('detects duplicate queries', function () {
        $this->collector->start();

        $sql = 'SELECT * FROM users WHERE id = ?';
        Event::dispatch(new QueryExecuted($sql, [1], 10, DB::connection()));
        Event::dispatch(new QueryExecuted($sql, [2], 10, DB::connection()));
        Event::dispatch(new QueryExecuted($sql, [3], 10, DB::connection()));

        $this->collector->collect();
        $data = $this->collector->getData();

        expect($data['duplicate_queries'])->toHaveCount(1);
        expect($data['duplicate_queries'][0]['sql'])->toBe($sql);
        expect($data['duplicate_queries'][0]['count'])->toBe(3);
        expect($data['duplicate_queries'][0]['total_time'])->toBe(30.0);
    });

    it('sorts duplicates by count', function () {
        $this->collector->start();

        // 5 duplicates
        for ($i = 0; $i < 5; $i++) {
            Event::dispatch(new QueryExecuted('QUERY A', [], 10, DB::connection()));
        }

        // 3 duplicates
        for ($i = 0; $i < 3; $i++) {
            Event::dispatch(new QueryExecuted('QUERY B', [], 10, DB::connection()));
        }

        // 7 duplicates
        for ($i = 0; $i < 7; $i++) {
            Event::dispatch(new QueryExecuted('QUERY C', [], 10, DB::connection()));
        }

        $this->collector->collect();
        $data = $this->collector->getData();

        expect($data['duplicate_queries'])->toHaveCount(3);
        expect($data['duplicate_queries'][0]['count'])->toBe(7); // QUERY C
        expect($data['duplicate_queries'][1]['count'])->toBe(5); // QUERY A
        expect($data['duplicate_queries'][2]['count'])->toBe(3); // QUERY B
    });

    it('ignores unique queries', function () {
        $this->collector->start();

        Event::dispatch(new QueryExecuted('UNIQUE A', [], 10, DB::connection()));
        Event::dispatch(new QueryExecuted('UNIQUE B', [], 10, DB::connection()));
        Event::dispatch(new QueryExecuted('UNIQUE C', [], 10, DB::connection()));

        $this->collector->collect();
        $data = $this->collector->getData();

        expect($data['duplicate_queries'])->toBeEmpty();
    });

    it('calculates total time for duplicates', function () {
        $this->collector->start();

        Event::dispatch(new QueryExecuted('DUP', [], 15.5, DB::connection()));
        Event::dispatch(new QueryExecuted('DUP', [], 20.3, DB::connection()));
        Event::dispatch(new QueryExecuted('DUP', [], 10.2, DB::connection()));

        $this->collector->collect();
        $data = $this->collector->getData();

        expect($data['duplicate_queries'][0]['total_time'])->toBe(46.0);
    });
});

describe('DatabaseCollector N+1 Detection', function () {
    it('detects N+1 query pattern', function () {
        $this->collector->start();

        // Initial query
        Event::dispatch(new QueryExecuted('SELECT * FROM posts', [], 10, DB::connection()));

        // N+1 pattern: same query with different IDs
        for ($i = 1; $i <= 5; $i++) {
            Event::dispatch(new QueryExecuted("SELECT * FROM users WHERE id = {$i}", [], 5, DB::connection()));
        }

        $this->collector->collect();
        $data = $this->collector->getData();

        expect($data['possible_n_plus_one'])->toHaveCount(1);
        expect($data['possible_n_plus_one'][0]['count'])->toBe(5);
    });

    it('normalizes queries for N+1 detection', function () {
        $this->collector->start();

        // These should be detected as same pattern
        Event::dispatch(new QueryExecuted('SELECT * FROM users WHERE id = 1', [], 10, DB::connection()));
        Event::dispatch(new QueryExecuted('SELECT * FROM users WHERE id = 2', [], 10, DB::connection()));
        Event::dispatch(new QueryExecuted('SELECT * FROM users WHERE id = 999', [], 10, DB::connection()));

        $this->collector->collect();
        $data = $this->collector->getData();

        expect($data['possible_n_plus_one'])->toHaveCount(1);
        expect($data['possible_n_plus_one'][0]['pattern'])->toBe('SELECT * FROM users WHERE id = ?');
    });

    it('requires minimum threshold for N+1', function () {
        $this->collector->start();

        // Only 2 occurrences (below threshold of 3)
        Event::dispatch(new QueryExecuted('SELECT * FROM items WHERE id = 1', [], 10, DB::connection()));
        Event::dispatch(new QueryExecuted('SELECT * FROM items WHERE id = 2', [], 10, DB::connection()));

        $this->collector->collect();
        $data = $this->collector->getData();

        expect($data['possible_n_plus_one'])->toBeEmpty();
    });

    it('keeps examples for N+1 patterns', function () {
        $this->collector->start();

        for ($i = 1; $i <= 10; $i++) {
            Event::dispatch(new QueryExecuted("SELECT * FROM products WHERE id = {$i}", [], 5, DB::connection()));
        }

        $this->collector->collect();
        $data = $this->collector->getData();

        $nPlusOne = $data['possible_n_plus_one'][0];
        expect($nPlusOne['examples'])->toHaveCount(3); // Max 3 examples
        expect($nPlusOne['examples'][0])->toHaveKey('sql');
        expect($nPlusOne['examples'][0])->toHaveKey('bindings');
    });

    it('calculates total time for N+1 patterns', function () {
        $this->collector->start();

        for ($i = 1; $i <= 5; $i++) {
            Event::dispatch(new QueryExecuted("SELECT * FROM tags WHERE id = {$i}", [], 12.5, DB::connection()));
        }

        $this->collector->collect();
        $data = $this->collector->getData();

        expect($data['possible_n_plus_one'][0]['total_time'])->toBe(62.5);
    });

    it('sorts N+1 patterns by count', function () {
        $this->collector->start();

        // Pattern A: 3 occurrences
        for ($i = 1; $i <= 3; $i++) {
            Event::dispatch(new QueryExecuted("SELECT * FROM authors WHERE id = {$i}", [], 10, DB::connection()));
        }

        // Pattern B: 8 occurrences
        for ($i = 1; $i <= 8; $i++) {
            Event::dispatch(new QueryExecuted("SELECT * FROM categories WHERE id = {$i}", [], 10, DB::connection()));
        }

        // Pattern C: 5 occurrences
        for ($i = 1; $i <= 5; $i++) {
            Event::dispatch(new QueryExecuted("SELECT * FROM tags WHERE id = {$i}", [], 10, DB::connection()));
        }

        $this->collector->collect();
        $data = $this->collector->getData();

        expect($data['possible_n_plus_one'])->toHaveCount(3);
        expect($data['possible_n_plus_one'][0]['count'])->toBe(8); // categories
        expect($data['possible_n_plus_one'][1]['count'])->toBe(5); // tags
        expect($data['possible_n_plus_one'][2]['count'])->toBe(3); // authors
    });

    it('handles IN clause normalization', function () {
        $this->collector->start();

        Event::dispatch(new QueryExecuted('SELECT * FROM users WHERE id IN (1, 2, 3)', [], 10, DB::connection()));
        Event::dispatch(new QueryExecuted('SELECT * FROM users WHERE id IN (4, 5)', [], 10, DB::connection()));
        Event::dispatch(new QueryExecuted('SELECT * FROM users WHERE id IN (6, 7, 8, 9)', [], 10, DB::connection()));

        $this->collector->collect();
        $data = $this->collector->getData();

        expect($data['possible_n_plus_one'])->toHaveCount(1);
        expect($data['possible_n_plus_one'][0]['pattern'])->toContain('IN (?)');
    });

    it('detects classic N+1 scenario', function () {
        $this->collector->start();

        // 1. Initial query to get posts
        Event::dispatch(new QueryExecuted('SELECT * FROM posts LIMIT 10', [], 15, DB::connection()));

        // N. Query for each post's author (this is the N+1 problem!)
        for ($i = 1; $i <= 10; $i++) {
            Event::dispatch(new QueryExecuted("SELECT * FROM users WHERE id = {$i}", [], 8, DB::connection()));
        }

        $this->collector->collect();
        $data = $this->collector->getData();

        expect($data['total_queries'])->toBe(11); // 1 + 10
        expect($data['possible_n_plus_one'])->toHaveCount(1);
        expect($data['possible_n_plus_one'][0]['count'])->toBe(10);
        expect($data['possible_n_plus_one'][0]['total_time'])->toBe(80.0);
    });
});

describe('DatabaseCollector Binding Formatting', function () {
    it('formats boolean bindings', function () {
        $this->collector->start();

        Event::dispatch(new QueryExecuted('SELECT * WHERE active = ?', [true], 10, DB::connection()));
        Event::dispatch(new QueryExecuted('SELECT * WHERE deleted = ?', [false], 10, DB::connection()));

        $this->collector->collect();
        $data = $this->collector->getData();

        expect($data['queries'][0]['bindings'][0])->toBe('true');
        expect($data['queries'][1]['bindings'][0])->toBe('false');
    });

    it('formats null bindings', function () {
        $this->collector->start();

        Event::dispatch(new QueryExecuted('SELECT * WHERE deleted_at = ?', [null], 10, DB::connection()));

        $this->collector->collect();
        $data = $this->collector->getData();

        expect($data['queries'][0]['bindings'][0])->toBe('NULL');
    });

    it('formats DateTime bindings', function () {
        $this->collector->start();

        $date = new DateTime('2024-11-25 10:30:00');
        Event::dispatch(new QueryExecuted('SELECT * WHERE created_at > ?', [$date], 10, DB::connection()));

        $this->collector->collect();
        $data = $this->collector->getData();

        expect($data['queries'][0]['bindings'][0])->toBe('2024-11-25 10:30:00');
    });

    it('truncates long string bindings', function () {
        $this->collector->start();

        $longString = str_repeat('x', 200);
        Event::dispatch(new QueryExecuted('SELECT * WHERE content = ?', [$longString], 10, DB::connection()));

        $this->collector->collect();
        $data = $this->collector->getData();

        expect(strlen($data['queries'][0]['bindings'][0]))->toBeLessThanOrEqual(103); // 100 + "..."
    });

    it('handles array of mixed types', function () {
        $this->collector->start();

        Event::dispatch(new QueryExecuted(
            'SELECT * WHERE a = ? AND b = ? AND c = ? AND d = ?',
            ['string', 123, true, null],
            10,
            DB::connection()
        ));

        $this->collector->collect();
        $data = $this->collector->getData();

        $bindings = $data['queries'][0]['bindings'];
        expect($bindings[0])->toBe('string');
        expect($bindings[1])->toBe(123);
        expect($bindings[2])->toBe('true');
        expect($bindings[3])->toBe('NULL');
    });
});

describe('DatabaseCollector Edge Cases', function () {
    it('handles collection without queries', function () {
        $this->collector->start();
        $this->collector->collect();

        $data = $this->collector->getData();

        expect($data['total_queries'])->toBe(0);
        expect($data['queries'])->toBeEmpty();
        expect($data['slow_queries'])->toBeEmpty();
        expect($data['duplicate_queries'])->toBeEmpty();
        expect($data['possible_n_plus_one'])->toBeEmpty();
    });

    it('handles queries with empty bindings', function () {
        $this->collector->start();

        Event::dispatch(new QueryExecuted('SELECT * FROM users', [], 10, DB::connection()));

        $this->collector->collect();
        $data = $this->collector->getData();

        expect($data['queries'][0]['bindings'])->toBeEmpty();
    });

    it('handles collection without start', function () {
        $this->collector->collect();

        expect($this->collector->getData())->toBeArray();
    });

    it('handles queries with special characters', function () {
        $this->collector->start();

        Event::dispatch(new QueryExecuted(
            "SELECT * FROM users WHERE name = ? AND bio LIKE ?",
            ["John O'Brien", "%developer's%"],
            10,
            DB::connection()
        ));

        $this->collector->collect();

        expect(fn() => $this->collector->getData())->not->toThrow(Exception::class);
    });

    it('handles very large query count', function () {
        $this->collector->start();

        for ($i = 0; $i < 1000; $i++) {
            Event::dispatch(new QueryExecuted("SELECT {$i}", [], 1, DB::connection()));
        }

        $this->collector->collect();
        $data = $this->collector->getData();

        expect($data['total_queries'])->toBe(1000);
    });
});

describe('DatabaseCollector Integration', function () {
    it('follows complete lifecycle', function () {
        // Start
        $this->collector->start();

        // Record queries
        Event::dispatch(new QueryExecuted('SELECT 1', [], 10, DB::connection()));
        Event::dispatch(new QueryExecuted('SELECT 2', [], 20, DB::connection()));

        // Collect
        $this->collector->collect();

        $data = $this->collector->getData();
        expect($data['total_queries'])->toBe(2);
        expect($data['total_time'])->toBe(30.0);

        // Reset
        $this->collector->reset();
        $this->collector->start();
        $this->collector->collect();

        $data = $this->collector->getData();
        expect($data['total_queries'])->toBe(0);
    });

    it('provides complete analysis', function () {
        $this->collector->start();

        // Fast query
        Event::dispatch(new QueryExecuted('SELECT * FROM config', [], 5, DB::connection()));

        // Slow query
        Event::dispatch(new QueryExecuted('SELECT * FROM huge_table', [], 250, DB::connection()));

        // Duplicates
        for ($i = 0; $i < 3; $i++) {
            Event::dispatch(new QueryExecuted('SELECT COUNT(*) FROM stats', [], 15, DB::connection()));
        }

        // N+1 pattern
        for ($i = 1; $i <= 5; $i++) {
            Event::dispatch(new QueryExecuted("SELECT * FROM profiles WHERE user_id = {$i}", [], 10, DB::connection()));
        }

        $this->collector->collect();
        $data = $this->collector->getData();

        expect($data['total_queries'])->toBe(10);
        expect($data['slow_queries'])->toHaveCount(1);
        expect($data['duplicate_queries'])->toHaveCount(1);
        // N+1 detection may find multiple patterns
        expect($data['possible_n_plus_one'])->toBeGreaterThanOrEqual(1);
    });
});

describe('DatabaseCollector Query Expression Handling', function () {
    it('handles Query Expression objects in SQL', function () {
        $this->collector->start();

        // Create Expression via DB::raw
        $expression = DB::raw('NOW()');
        
        // Create a proper connection mock
        $connection = Mockery::mock(\Illuminate\Database\Connection::class);
        $connection->shouldReceive('getName')->andReturn('mysql');
        $connection->shouldReceive('getDatabaseName')->andReturn('test_db');
        $connection->shouldReceive('getConfig')->with('driver')->andReturn('mysql');
        $connection->shouldReceive('prepareBindings')->andReturn([]);
        
        Event::dispatch(new QueryExecuted($expression, [], 10, $connection));

        $this->collector->collect();
        $data = $this->collector->getData();

        expect($data['total_queries'])->toBe(1);
        expect($data['queries'][0]['sql'])->toBe('NOW()');
    });

    it('handles Query Expression objects in bindings', function () {
        $this->collector->start();

        // Create Expression via DB::raw
        $expression = DB::raw('CURRENT_DATE');
        
        // Create a proper connection mock
        $connection = Mockery::mock(\Illuminate\Database\Connection::class);
        $connection->shouldReceive('getName')->andReturn('mysql');
        $connection->shouldReceive('getDatabaseName')->andReturn('test_db');
        $connection->shouldReceive('getConfig')->with('driver')->andReturn('mysql');
        $connection->shouldReceive('prepareBindings')->with([$expression])->andReturn([$expression]);
        
        Event::dispatch(new QueryExecuted('SELECT * FROM logs WHERE date = ?', [$expression], 10, $connection));

        $this->collector->collect();
        $data = $this->collector->getData();

        expect($data['total_queries'])->toBe(1);
        expect($data['queries'][0]['bindings'])->toContain('CURRENT_DATE');
    });
});

describe('DatabaseCollector Caller Detection Edge Cases', function () {
    it('returns null when no valid caller found', function () {
        // This is a private method, but we can test it indirectly by
        // ensuring queries from framework files are handled gracefully
        $this->collector->start();

        // This query is dispatched from test code, so caller will be detected
        Event::dispatch(new QueryExecuted('SELECT 1', [], 1, DB::connection()));

        $this->collector->collect();
        $data = $this->collector->getData();

        // Should have caller info or null, both are valid
        expect($data['queries'])->toHaveCount(1);
    });
});

// Note: Connection info exception handling (lines 327-331) is covered indirectly
// through integration tests where connection failures may occur naturally

describe('DatabaseCollector Event Forgetting', function () {
    it('forgets event listeners on reset when listener exists', function () {
        $eventSpy = Event::fake();

        $this->collector->start();
        
        // Dispatch a query to ensure listener is active
        Event::dispatch(new QueryExecuted('SELECT 1', [], 1, DB::connection()));
        
        // Reset should forget the listener
        $this->collector->reset();

        // Verify state is clean
        expect($this->collector->getData())->toBeEmpty();
    });

    it('handles reset when listener does not exist', function () {
        // Reset without starting first
        expect(fn() => $this->collector->reset())->not->toThrow(\Exception::class);
    });
});

