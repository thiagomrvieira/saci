<?php

namespace ThiagoVieira\Saci\Collectors;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Collects authentication and authorization information.
 *
 * Responsibilities:
 * - Current user details
 * - Authentication guard
 * - User identification
 */
class AuthCollector extends BaseCollector
{
    protected ?Request $request = null;

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'auth';
    }

    /**
     * {@inheritdoc}
     */
    public function getLabel(): string
    {
        return 'Auth';
    }

    /**
     * Set the request for collection.
     */
    public function setRequest(Request $request): void
    {
        $this->request = $request;
    }

    /**
     * {@inheritdoc}
     */
    protected function doCollect(): void
    {
        try {
            $defaultGuard = config('auth.defaults.guard');
            $user = Auth::guard($defaultGuard)->user();

            $this->data = [
                'guard' => $defaultGuard,
                'authenticated' => $user ? true : false,
                'id' => $user && method_exists($user, 'getAuthIdentifier') ? $user->getAuthIdentifier() : null,
                'email' => $user && isset($user->email) ? $user->email : null,
                'name' => $user && isset($user->name) ? $user->name : null,
            ];
        } catch (\Throwable $e) {
            $this->data = [
                'guard' => null,
                'authenticated' => false,
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function doReset(): void
    {
        $this->request = null;
    }
}


