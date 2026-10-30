<?php

namespace App\Http\Middleware;

use App\Enums\StaffArea;
use App\Services\CartService;
use App\Services\OpeningHours;
use App\Support\Seo;
use App\Support\Settings;
use App\Support\Translations;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $scope = $this->translationScope($request);

        return [
            ...parent::share($request),
            'name' => config('restaurant.name'),
            'restaurant' => Inertia::once(fn () => [
                'name' => config('restaurant.name'),
                'phone' => config('restaurant.contact.phone'),
                'email' => config('restaurant.contact.email'),
                'address' => config('restaurant.contact.address'),
                'company' => config('restaurant.company'),
            ]),
            'footer' => Inertia::once(fn () => [
                'hours' => app(OpeningHours::class)->week(now()->startOfWeek()),
                'hygieneRating' => is_numeric($rating = app(Settings::class)->get('hygiene_rating'))
                    ? (int) $rating
                    : $rating,
            ]),
            'seo' => fn () => Seo::for($request),
            // Keyed by audience, so signing in or out swaps the cached set.
            'translations' => Inertia::once(fn () => $this->translations($scope))->as("translations-{$scope}"),
            'auth' => [
                'user' => $request->user(),
            ],
            'basketCount' => fn () => $request->user()?->isStaff() ? 0 : app(CartService::class)->count(),
            'openStatus' => fn () => app(OpeningHours::class)->status(),
            'staffAreas' => fn () => $request->user()?->isStaff()
                ? array_map(fn (StaffArea $area) => $area->value, StaffArea::for($request->user()->role))
                : [],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * Who the interface strings are for: staff get every group, a guest
     * opening a staff invite gets the team group it needs, anyone else gets
     * the public groups only.
     */
    private function translationScope(Request $request): string
    {
        if ($request->user()?->isStaff()) {
            return 'staff';
        }

        return $request->routeIs('invite.*') ? 'invite' : 'public';
    }

    /**
     * @return array<string, mixed>
     */
    private function translations(string $scope): array
    {
        $groups = Translations::forLocale(app()->getLocale());

        if ($scope === 'staff') {
            return $groups;
        }

        return array_filter(
            $groups,
            fn (string $group) => ! str_starts_with($group, 'staff') || ($scope === 'invite' && $group === 'staff_team'),
            ARRAY_FILTER_USE_KEY,
        );
    }
}
