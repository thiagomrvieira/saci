<?php

declare(strict_types=1);

use ThiagoVieira\Saci\RequestValidator;
use ThiagoVieira\Saci\SaciConfig;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

beforeEach(function () {
    $this->validator = new RequestValidator();
});

describe('RequestValidator shouldTrace', function () {
    it('returns false for Saci own endpoints', function () {
        $request = Request::create('/__saci/dump/123');

        expect($this->validator->shouldTrace($request))->toBeFalse();
    });

    it('returns false when Saci is disabled', function () {
        config()->set('saci.enabled', false);

        $request = Request::create('/test');
        $request->headers->set('Accept', 'text/html');

        expect($this->validator->shouldTrace($request))->toBeFalse();
    });

    it('returns false when request does not accept HTML', function () {
        config()->set('saci.enabled', true);

        $request = Request::create('/test');
        $request->headers->set('Accept', 'application/json');

        expect($this->validator->shouldTrace($request))->toBeFalse();
    });

    it('returns false for AJAX requests when ajax not allowed', function () {
        config()->set('saci.enabled', true);
        config()->set('saci.allow_ajax', false);

        $request = Request::create('/test');
        $request->headers->set('Accept', 'text/html');
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');

        expect($this->validator->shouldTrace($request))->toBeFalse();
    });

    it('returns true for AJAX requests when ajax is allowed', function () {
        config()->set('saci.enabled', true);
        config()->set('saci.allow_ajax', true);
        config()->set('saci.allow_ips', []);

        $request = Request::create('/test');
        $request->headers->set('Accept', 'text/html');
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');

        expect($this->validator->shouldTrace($request))->toBeTrue();
    });

    it('returns false when client IP is not allowed', function () {
        config()->set('saci.enabled', true);
        config()->set('saci.allow_ips', ['192.168.1.1']);

        $request = Request::create('/test');
        $request->headers->set('Accept', 'text/html');
        $request->server->set('REMOTE_ADDR', '10.0.0.1');

        expect($this->validator->shouldTrace($request))->toBeFalse();
    });

    it('returns true when client IP is in allowed list', function () {
        config()->set('saci.enabled', true);
        config()->set('saci.allow_ips', ['127.0.0.1']);

        $request = Request::create('/test');
        $request->headers->set('Accept', 'text/html');
        $request->server->set('REMOTE_ADDR', '127.0.0.1');

        expect($this->validator->shouldTrace($request))->toBeTrue();
    });

    it('returns true for valid HTML request', function () {
        config()->set('saci.enabled', true);
        config()->set('saci.allow_ips', []);

        $request = Request::create('/test');
        $request->headers->set('Accept', 'text/html');

        expect($this->validator->shouldTrace($request))->toBeTrue();
    });

    it('skips JSON-accepting non-ajax clients', function () {
        config()->set('saci.enabled', true);
        config()->set('saci.allow_ajax', false);

        $request = Request::create('/api/test');
        $request->headers->set('Accept', 'application/json, text/html');

        expect($this->validator->shouldTrace($request))->toBeFalse();
    });

    it('uses app.debug when enabled config is null', function () {
        config()->set('saci.enabled', null);
        config()->set('app.debug', true);
        config()->set('saci.allow_ips', []);

        $request = Request::create('/test');
        $request->headers->set('Accept', 'text/html');

        expect($this->validator->shouldTrace($request))->toBeTrue();
    });

    it('respects app.debug false when enabled is null', function () {
        config()->set('saci.enabled', null);
        config()->set('app.debug', false);

        $request = Request::create('/test');
        $request->headers->set('Accept', 'text/html');

        expect($this->validator->shouldTrace($request))->toBeFalse();
    });
});

describe('RequestValidator shouldServeDump', function () {
    it('returns false for non-dump paths', function () {
        $request = Request::create('/regular-page');

        expect($this->validator->shouldServeDump($request))->toBeFalse();
    });

    it('returns true for dump paths', function () {
        config()->set('saci.enabled', true);
        config()->set('saci.allow_ips', []);

        $request = Request::create('/__saci/dump/abc123');

        expect($this->validator->shouldServeDump($request))->toBeTrue();
    });

    it('returns false when disabled', function () {
        config()->set('saci.enabled', false);

        $request = Request::create('/__saci/dump/abc123');

        expect($this->validator->shouldServeDump($request))->toBeFalse();
    });

    it('returns false when IP not allowed', function () {
        config()->set('saci.enabled', true);
        config()->set('saci.allow_ips', ['192.168.1.1']);

        $request = Request::create('/__saci/dump/abc123');
        $request->server->set('REMOTE_ADDR', '10.0.0.1');

        expect($this->validator->shouldServeDump($request))->toBeFalse();
    });

    it('allows AJAX requests to dumps', function () {
        config()->set('saci.enabled', true);
        config()->set('saci.allow_ips', []);

        $request = Request::create('/__saci/dump/abc123');
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');

        expect($this->validator->shouldServeDump($request))->toBeTrue();
    });
});

describe('RequestValidator shouldServeAssets', function () {
    it('returns true when enabled and no IP restriction', function () {
        config()->set('saci.enabled', true);
        config()->set('saci.allow_ips', []);

        $request = Request::create('/__saci/assets/app.css');

        expect($this->validator->shouldServeAssets($request))->toBeTrue();
    });

    it('returns false when disabled', function () {
        config()->set('saci.enabled', false);

        $request = Request::create('/__saci/assets/app.css');

        expect($this->validator->shouldServeAssets($request))->toBeFalse();
    });

    it('returns false when IP not allowed', function () {
        config()->set('saci.enabled', true);
        config()->set('saci.allow_ips', ['192.168.1.1']);

        $request = Request::create('/__saci/assets/app.css');
        $request->server->set('REMOTE_ADDR', '10.0.0.1');

        expect($this->validator->shouldServeAssets($request))->toBeFalse();
    });

    it('returns true when client IP matches', function () {
        config()->set('saci.enabled', true);
        config()->set('saci.allow_ips', ['127.0.0.1']);

        $request = Request::create('/__saci/assets/app.css');
        $request->server->set('REMOTE_ADDR', '127.0.0.1');

        expect($this->validator->shouldServeAssets($request))->toBeTrue();
    });
});

describe('RequestValidator shouldSkipResponse', function () {
    it('returns true for BinaryFileResponse', function () {
        $response = Mockery::mock(BinaryFileResponse::class);

        expect($this->validator->shouldSkipResponse($response))->toBeTrue();
    });

    it('returns true for StreamedResponse', function () {
        $response = Mockery::mock(StreamedResponse::class);

        expect($this->validator->shouldSkipResponse($response))->toBeTrue();
    });

    it('returns false for regular Response', function () {
        $response = new \Illuminate\Http\Response('test');

        expect($this->validator->shouldSkipResponse($response))->toBeFalse();
    });

    it('returns false for JsonResponse', function () {
        $response = new \Illuminate\Http\JsonResponse(['data' => 'test']);

        expect($this->validator->shouldSkipResponse($response))->toBeFalse();
    });
});

describe('RequestValidator IP Filtering', function () {
    it('allows all IPs when allow_ips is empty', function () {
        config()->set('saci.enabled', true);
        config()->set('saci.allow_ips', []);

        $request = Request::create('/test');
        $request->headers->set('Accept', 'text/html');
        $request->server->set('REMOTE_ADDR', '1.2.3.4');

        expect($this->validator->shouldTrace($request))->toBeTrue();
    });

    it('allows multiple IPs in whitelist', function () {
        config()->set('saci.enabled', true);
        config()->set('saci.allow_ips', ['127.0.0.1', '192.168.1.1', '10.0.0.1']);

        // Test first IP
        $request1 = Request::create('/test');
        $request1->headers->set('Accept', 'text/html');
        $request1->server->set('REMOTE_ADDR', '127.0.0.1');
        expect($this->validator->shouldTrace($request1))->toBeTrue();

        // Test middle IP
        $request2 = Request::create('/test');
        $request2->headers->set('Accept', 'text/html');
        $request2->server->set('REMOTE_ADDR', '192.168.1.1');
        expect($this->validator->shouldTrace($request2))->toBeTrue();

        // Test last IP
        $request3 = Request::create('/test');
        $request3->headers->set('Accept', 'text/html');
        $request3->server->set('REMOTE_ADDR', '10.0.0.1');
        expect($this->validator->shouldTrace($request3))->toBeTrue();
    });

    it('blocks IP not in whitelist', function () {
        config()->set('saci.enabled', true);
        config()->set('saci.allow_ips', ['127.0.0.1', '192.168.1.1']);

        $request = Request::create('/test');
        $request->headers->set('Accept', 'text/html');
        $request->server->set('REMOTE_ADDR', '10.0.0.1');

        expect($this->validator->shouldTrace($request))->toBeFalse();
    });
});

describe('RequestValidator Edge Cases', function () {
    it('handles requests with various Accept headers', function () {
        config()->set('saci.enabled', true);
        config()->set('saci.allow_ips', []);

        // Accept: text/html,application/xhtml+xml
        $request1 = Request::create('/test');
        $request1->headers->set('Accept', 'text/html,application/xhtml+xml,application/xml;q=0.9');
        expect($this->validator->shouldTrace($request1))->toBeTrue();

        // Accept: */*
        $request2 = Request::create('/test');
        $request2->headers->set('Accept', '*/*');
        expect($this->validator->shouldTrace($request2))->toBeTrue();
    });

    it('handles Saci paths with trailing slashes', function () {
        $request = Request::create('/__saci/dump/');

        expect($this->validator->shouldTrace($request))->toBeFalse();
    });

    it('handles deep Saci paths', function () {
        $request = Request::create('/__saci/assets/css/app.css');

        expect($this->validator->shouldTrace($request))->toBeFalse();
    });

    it('case-sensitive path matching', function () {
        config()->set('saci.enabled', true);
        config()->set('saci.allow_ips', []);

        $request = Request::create('/__SACI/dump/123');
        $request->headers->set('Accept', 'text/html');

        // str_starts_with is case-sensitive, so __SACI != __saci
        // This path will NOT be blocked (treated as regular path)
        expect($this->validator->shouldTrace($request))->toBeTrue();
    });
});

