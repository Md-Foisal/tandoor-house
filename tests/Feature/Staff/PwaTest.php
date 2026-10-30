<?php

use App\Enums\Role;
use App\Models\User;

it('serves a staff-only manifest to anyone', function () {
    $manifest = $this->get('/staff/manifest.webmanifest')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/manifest+json')
        ->json();

    expect($manifest)->toMatchArray([
        'name' => config('restaurant.name').' Staff',
        'scope' => '/staff/',
        'start_url' => '/staff',
        'display' => 'standalone',
        'orientation' => 'any',
    ])
        ->and(collect($manifest['icons'])->pluck('purpose')->all())->toContain('maskable')
        ->and(collect($manifest['shortcuts'])->pluck('url')->all())
        ->toBe(['/staff/orders', '/staff/kitchen', '/staff/floor']);

    foreach ($manifest['icons'] as $icon) {
        expect(public_path($icon['src']))->toBeFile();
    }
});

it('serves a branded offline page without signing in', function () {
    $this->get('/staff/offline')
        ->assertOk()
        ->assertSee(config('restaurant.name'))
        ->assertSee('This tablet is offline')
        ->assertDontSee('{{');
});

it('ships a service worker that keeps only the offline page and icons', function () {
    $worker = file_get_contents(public_path('staff-sw.js'));

    expect($worker)->toContain("'/staff/offline'")
        ->and($worker)->not->toContain('cache.put')
        ->and($worker)->toContain('key !== CACHE');
});

it('links the manifest from the staff layout only', function () {
    $staffLayout = file_get_contents(resource_path('js/layouts/staff-layout.tsx'));
    $pwa = file_get_contents(resource_path('js/components/staff/install-app.tsx'));

    expect($staffLayout)->toContain('<StaffPwaHead />')
        ->and($pwa)->toContain('/staff/manifest.webmanifest');

    foreach (glob(resource_path('js/layouts/*.tsx')) as $layout) {
        if (basename($layout) !== 'staff-layout.tsx') {
            expect(file_get_contents($layout))->not->toContain('manifest.webmanifest');
        }
    }

    $this->get('/')->assertOk()->assertDontSee('manifest.webmanifest');
    $this->actingAs(User::factory()->role(Role::Waiter)->create())->get('/staff')->assertOk();
});
