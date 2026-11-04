<?php

use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->withoutVite());

it('renders the privacy notice with the controller details', function () {
    config(['restaurant.company.ico_number' => null]);

    $this->get('/privacy')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('legal/privacy')
            ->where('icoRegistered', false)
            ->where('email', config('restaurant.contact.email'))
            ->has('sections', 10)
            ->where('sections.0.id', 'who')
            ->where('sections.0.paragraphs.0', fn (string $text) => str_contains($text, config('restaurant.company.legal_name'))
                && ! str_contains($text, ':legal'))
            ->where('sections.0.paragraphs.2', fn (string $text) => str_contains($text, 'pending'))
            ->has('translations.legal.consent.reject'));
});

it('shows the ICO number once it is configured', function () {
    config(['restaurant.company.ico_number' => 'ZB000000']);

    $this->get('/privacy')
        ->assertInertia(fn (Assert $page) => $page
            ->where('icoRegistered', true)
            ->where('sections.0.paragraphs.2', fn (string $text) => str_contains($text, 'ZB000000')));
});

it('lists every configured cookie with a plain duration', function () {
    config(['payments.stripe.key' => null, 'session.lifetime' => 120]);

    $this->get('/cookies')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('legal/cookies')
            ->where('items', function ($items) {
                $items = collect($items);
                $names = $items->pluck('name');

                return $names->contains('XSRF-TOKEN')
                    && $names->contains('cookie_consent')
                    && $names->contains(config('session.cookie'))
                    && ! $names->contains('__stripe_mid')
                    && $items->firstWhere('id', 'xsrf')['duration'] === '2 hours'
                    && $items->firstWhere('id', 'consent')['duration'] === '1 year'
                    && $items->firstWhere('id', 'sidebar')['category'] === 'preferences'
                    && $items->every(fn (array $item) => $item['purpose'] !== '' && ! str_starts_with($item['purpose'], 'legal.'));
            }));
});

it('includes the Stripe cookies only when Stripe is configured', function () {
    config(['payments.stripe.key' => 'pk_test_123']);

    $this->get('/cookies')
        ->assertInertia(fn (Assert $page) => $page
            ->where('items', fn ($items) => collect($items)->pluck('name')->contains('__stripe_mid')));
});

it('keeps the consent cookie settings in step with the front end', function () {
    $source = file_get_contents(resource_path('js/components/consent/consent-store.ts'));

    expect($source)
        ->toContain("CONSENT_COOKIE = '".config('cookies.consent.name')."'")
        ->toContain('CONSENT_VERSION = '.config('cookies.consent.version'))
        ->toContain('CONSENT_DAYS = '.config('cookies.consent.days'));
});

it('lets a signed-out visitor read both pages', function () {
    $this->get('/privacy')->assertOk();
    $this->get('/cookies')->assertOk();
});
