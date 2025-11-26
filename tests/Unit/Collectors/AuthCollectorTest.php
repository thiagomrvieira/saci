<?php

declare(strict_types=1);

use ThiagoVieira\Saci\Collectors\AuthCollector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

beforeEach(function () {
    $this->collector = new AuthCollector();
});

describe('AuthCollector Identity', function () {
    it('returns correct name', function () {
        expect($this->collector->getName())->toBe('auth');
    });

    it('returns correct label', function () {
        expect($this->collector->getLabel())->toBe('Auth');
    });

    it('is enabled by default', function () {
        config()->set('saci.collectors.auth', true);
        expect($this->collector->isEnabled())->toBeTrue();
    });
});

describe('AuthCollector Lifecycle', function () {
    it('accepts request via setRequest', function () {
        $request = Request::create('/test', 'GET');

        expect(fn() => $this->collector->setRequest($request))->not->toThrow(Exception::class);
    });

    it('resets state on reset', function () {
        $request = Request::create('/test', 'GET');

        $this->collector->setRequest($request);
        $this->collector->reset();

        expect(true)->toBeTrue(); // No exception thrown
    });
});

describe('AuthCollector Guest User', function () {
    it('detects guest user', function () {
        config()->set('auth.defaults.guard', 'web');
        Auth::shouldReceive('guard')->with('web')->andReturnSelf();
        Auth::shouldReceive('user')->andReturn(null);

        $this->collector->start();
        $this->collector->collect();

        $data = $this->collector->getData();

        expect($data['authenticated'])->toBeFalse();
        expect($data['guard'])->toBe('web');
        expect($data['id'])->toBeNull();
        expect($data['email'])->toBeNull();
        expect($data['name'])->toBeNull();
    });

    it('includes guard name for guest', function () {
        config()->set('auth.defaults.guard', 'api');
        Auth::shouldReceive('guard')->with('api')->andReturnSelf();
        Auth::shouldReceive('user')->andReturn(null);

        $this->collector->start();
        $this->collector->collect();

        $data = $this->collector->getData();

        expect($data['guard'])->toBe('api');
        expect($data['authenticated'])->toBeFalse();
    });
});

describe('AuthCollector Authenticated User', function () {
    it('detects authenticated user', function () {
        config()->set('auth.defaults.guard', 'web');

        $user = new class {
            public $email = 'john@example.com';
            public $name = 'John Doe';
            public function getAuthIdentifier() { return 1; }
        };

        Auth::shouldReceive('guard')->with('web')->andReturnSelf();
        Auth::shouldReceive('user')->andReturn($user);

        $this->collector->start();
        $this->collector->collect();

        $data = $this->collector->getData();

        expect($data['authenticated'])->toBeTrue();
        expect($data['id'])->toBe(1);
        expect($data['email'])->toBe('john@example.com');
        expect($data['name'])->toBe('John Doe');
    });

    it('collects user ID', function () {
        config()->set('auth.defaults.guard', 'web');

        $user = new class {
            public $email = 'test@example.com';
            public $name = 'Test User';
            public function getAuthIdentifier() { return 42; }
        };

        Auth::shouldReceive('guard')->with('web')->andReturnSelf();
        Auth::shouldReceive('user')->andReturn($user);

        $this->collector->start();
        $this->collector->collect();

        $data = $this->collector->getData();

        expect($data['id'])->toBe(42);
    });

    it('collects user email', function () {
        config()->set('auth.defaults.guard', 'web');

        $user = new class {
            public $email = 'jane@example.com';
            public $name = 'Jane';
            public function getAuthIdentifier() { return 1; }
        };

        Auth::shouldReceive('guard')->with('web')->andReturnSelf();
        Auth::shouldReceive('user')->andReturn($user);

        $this->collector->start();
        $this->collector->collect();

        $data = $this->collector->getData();

        expect($data['email'])->toBe('jane@example.com');
    });

    it('collects user name', function () {
        config()->set('auth.defaults.guard', 'web');

        $user = new class {
            public $email = 'admin@example.com';
            public $name = 'Administrator';
            public function getAuthIdentifier() { return 1; }
        };

        Auth::shouldReceive('guard')->with('web')->andReturnSelf();
        Auth::shouldReceive('user')->andReturn($user);

        $this->collector->start();
        $this->collector->collect();

        $data = $this->collector->getData();

        expect($data['name'])->toBe('Administrator');
    });
});

describe('AuthCollector Different Guards', function () {
    it('works with web guard', function () {
        config()->set('auth.defaults.guard', 'web');

        $user = new class {
            public $email = 'web@example.com';
            public $name = 'Web User';
            public function getAuthIdentifier() { return 1; }
        };

        Auth::shouldReceive('guard')->with('web')->andReturnSelf();
        Auth::shouldReceive('user')->andReturn($user);

        $this->collector->start();
        $this->collector->collect();

        $data = $this->collector->getData();

        expect($data['guard'])->toBe('web');
        expect($data['authenticated'])->toBeTrue();
    });

    it('works with api guard', function () {
        config()->set('auth.defaults.guard', 'api');

        $user = new class {
            public $email = 'api@example.com';
            public $name = 'API User';
            public function getAuthIdentifier() { return 123; }
        };

        Auth::shouldReceive('guard')->with('api')->andReturnSelf();
        Auth::shouldReceive('user')->andReturn($user);

        $this->collector->start();
        $this->collector->collect();

        $data = $this->collector->getData();

        expect($data['guard'])->toBe('api');
        expect($data['authenticated'])->toBeTrue();
    });

    it('works with custom guard', function () {
        config()->set('auth.defaults.guard', 'admin');

        $user = new class {
            public $email = 'admin@example.com';
            public $name = 'Admin';
            public function getAuthIdentifier() { return 999; }
        };

        Auth::shouldReceive('guard')->with('admin')->andReturnSelf();
        Auth::shouldReceive('user')->andReturn($user);

        $this->collector->start();
        $this->collector->collect();

        $data = $this->collector->getData();

        expect($data['guard'])->toBe('admin');
    });
});

describe('AuthCollector Edge Cases', function () {
    it('handles user without email', function () {
        config()->set('auth.defaults.guard', 'web');

        $user = Mockery::mock();
        $user->shouldReceive('getAuthIdentifier')->andReturn(1);
        $user->name = 'User Without Email';
        // No email property

        Auth::shouldReceive('guard')->with('web')->andReturnSelf();
        Auth::shouldReceive('user')->andReturn($user);

        $this->collector->start();
        $this->collector->collect();

        $data = $this->collector->getData();

        expect($data['authenticated'])->toBeTrue();
        expect($data['email'])->toBeNull();
        expect($data['name'])->toBe('User Without Email');
    });

    it('handles user without name', function () {
        config()->set('auth.defaults.guard', 'web');

        $user = Mockery::mock();
        $user->shouldReceive('getAuthIdentifier')->andReturn(1);
        $user->email = 'noname@example.com';
        // No name property

        Auth::shouldReceive('guard')->with('web')->andReturnSelf();
        Auth::shouldReceive('user')->andReturn($user);

        $this->collector->start();
        $this->collector->collect();

        $data = $this->collector->getData();

        expect($data['authenticated'])->toBeTrue();
        expect($data['name'])->toBeNull();
        expect($data['email'])->toBe('noname@example.com');
    });

    it('handles user without getAuthIdentifier method', function () {
        config()->set('auth.defaults.guard', 'web');

        $user = new stdClass();
        $user->email = 'test@example.com';
        $user->name = 'Test';

        Auth::shouldReceive('guard')->with('web')->andReturnSelf();
        Auth::shouldReceive('user')->andReturn($user);

        $this->collector->start();
        $this->collector->collect();

        $data = $this->collector->getData();

        expect($data['authenticated'])->toBeTrue();
        expect($data['id'])->toBeNull();
    });

    it('handles auth exceptions gracefully', function () {
        config()->set('auth.defaults.guard', 'web');

        Auth::shouldReceive('guard')->with('web')->andThrow(new Exception('Auth error'));

        $this->collector->start();
        $this->collector->collect();

        $data = $this->collector->getData();

        expect($data['authenticated'])->toBeFalse();
        expect($data['guard'])->toBeNull();
    });

    it('handles missing auth configuration', function () {
        config()->set('auth.defaults.guard', null);

        Auth::shouldReceive('guard')->with(null)->andThrow(new Exception('No guard'));

        $this->collector->start();
        $this->collector->collect();

        $data = $this->collector->getData();

        expect($data['authenticated'])->toBeFalse();
    });

    it('handles collection without start', function () {
        $this->collector->collect();

        // Should not throw exception
        expect($this->collector->getData())->toBeArray();
    });
});

describe('AuthCollector Integration', function () {
    it('follows complete lifecycle with guest', function () {
        config()->set('auth.defaults.guard', 'web');
        Auth::shouldReceive('guard')->with('web')->andReturnSelf();
        Auth::shouldReceive('user')->andReturn(null);

        // Start
        $this->collector->start();

        // Collect
        $this->collector->collect();

        $data = $this->collector->getData();
        expect($data['authenticated'])->toBeFalse();

        // Reset
        $this->collector->reset();
    });

    it('follows complete lifecycle with authenticated user', function () {
        config()->set('auth.defaults.guard', 'web');

        $user = new class {
            public $email = 'user@example.com';
            public $name = 'Test User';
            public function getAuthIdentifier() { return 1; }
        };

        Auth::shouldReceive('guard')->with('web')->andReturnSelf();
        Auth::shouldReceive('user')->andReturn($user);

        // Start
        $this->collector->start();

        // Collect
        $this->collector->collect();

        $data = $this->collector->getData();
        expect($data['authenticated'])->toBeTrue();
        expect($data['id'])->toBe(1);

        // Reset
        $this->collector->reset();
    });

    it('handles multiple collection cycles', function () {
        config()->set('auth.defaults.guard', 'web');

        // First cycle - guest
        Auth::shouldReceive('guard')->with('web')->andReturnSelf();
        Auth::shouldReceive('user')->andReturn(null)->once();

        $this->collector->start();
        $this->collector->collect();

        expect($this->collector->getData()['authenticated'])->toBeFalse();

        $this->collector->reset();

        // Second cycle - authenticated
        $user = new class {
            public $email = 'new@example.com';
            public $name = 'New User';
            public function getAuthIdentifier() { return 42; }
        };

        Auth::shouldReceive('guard')->with('web')->andReturnSelf();
        Auth::shouldReceive('user')->andReturn($user)->once();

        $this->collector->start();
        $this->collector->collect();

        expect($this->collector->getData()['authenticated'])->toBeTrue();
        expect($this->collector->getData()['id'])->toBe(42);
    });
});

describe('AuthCollector Data Structure', function () {
    it('returns correct data structure for guest', function () {
        config()->set('auth.defaults.guard', 'web');
        Auth::shouldReceive('guard')->with('web')->andReturnSelf();
        Auth::shouldReceive('user')->andReturn(null);

        $this->collector->start();
        $this->collector->collect();

        $data = $this->collector->getData();

        expect($data)->toHaveKeys(['guard', 'authenticated', 'id', 'email', 'name']);
        expect($data)->toHaveCount(5);
    });

    it('returns correct data structure for authenticated user', function () {
        config()->set('auth.defaults.guard', 'web');

        $user = new class {
            public $email = 'test@example.com';
            public $name = 'Test';
            public function getAuthIdentifier() { return 1; }
        };

        Auth::shouldReceive('guard')->with('web')->andReturnSelf();
        Auth::shouldReceive('user')->andReturn($user);

        $this->collector->start();
        $this->collector->collect();

        $data = $this->collector->getData();

        expect($data)->toHaveKeys(['guard', 'authenticated', 'id', 'email', 'name']);
        expect($data)->toHaveCount(5);
    });
});

