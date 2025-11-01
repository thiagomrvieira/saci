<?php

namespace ThiagoVieira\Saci\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Response;
use ThiagoVieira\Saci\Support\DumpStorage;
use ThiagoVieira\Saci\Support\LateLogsPersistence;
use ThiagoVieira\Saci\RequestValidator;

class DumpController extends Controller
{
    public function __construct(
        protected DumpStorage $storage,
        protected RequestValidator $validator,
        protected LateLogsPersistence $lateLogsPersistence,
    ) {}

    public function show(Request $request, string $requestId, string $dumpId)
    {
        if (!$this->validator->shouldServeDump($request)) {
            return Response::make('Forbidden', 403);
        }

        $html = $this->storage->getHtml($requestId, $dumpId);
        if ($html === null) {
            return Response::make('Not Found', 404);
        }

        return Response::make($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    /**
     * Fetch late logs that were collected after the response was sent.
     * These are logs from terminable middleware, shutdown handlers, etc.
     */
    public function lateLogs(Request $request, string $requestId)
    {
        if (!$this->validator->shouldServeDump($request)) {
            return Response::json(['error' => 'Forbidden'], 403);
        }

        $data = $this->lateLogsPersistence->retrieve($requestId);

        return Response::json($data, 200, [
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }
}


