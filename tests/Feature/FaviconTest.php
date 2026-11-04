<?php

it('links icons that exist under names no web server special-cases', function () {
    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->not->toContain('href="/favicon.ico"');

    foreach (['icon.ico', 'icon.svg', 'apple-touch-icon.png'] as $icon) {
        expect($html)->toContain("href=\"/{$icon}\"")
            ->and(file_exists(public_path($icon)))->toBeTrue();
    }
});
