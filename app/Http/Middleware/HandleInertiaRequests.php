<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'availableLocales' => fn (): array => ['pt', 'en', 'es'],
            'auth' => [
                'user' => $request->user(),
            ],
            'flash' => [
                'discovery' => fn () => $request->session()->get('discovery'),
                'discovery_batch_id' => fn () => $request->session()->get('discovery_batch_id'),
                'discovery_created_count' => fn () => $request->session()->get('discovery_created_count'),
                'discovery_search_query' => fn () => $request->session()->get('discovery_search_query'),
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'locale' => fn (): string => app()->getLocale(),
            'translations' => fn (): array => trans('app'),
        ];
    }
}
