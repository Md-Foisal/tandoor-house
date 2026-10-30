<?php

use App\Models\Review;
use Tests\Feature\Policies\RoleMatrix;

dataset('policy review writes', [
    'publish in bulk' => ['post', fn () => ['/staff/reviews/publish', ['ids' => [Review::factory()->create(['is_published' => false])->id]]], RoleMatrix::MANAGEMENT],
    'publish one' => ['post', fn () => '/staff/reviews/'.Review::factory()->create(['is_published' => false])->id.'/publish', RoleMatrix::MANAGEMENT],
    'hide one' => ['post', fn () => '/staff/reviews/'.Review::factory()->create()->id.'/hide', RoleMatrix::MANAGEMENT],
    'reply' => ['put', fn () => '/staff/reviews/'.Review::factory()->create()->id.'/reply', RoleMatrix::MANAGEMENT],
    'remove a reply' => ['delete', fn () => '/staff/reviews/'.Review::factory()->create(['reply' => 'Thank you'])->id.'/reply', RoleMatrix::MANAGEMENT],
]);

it('guards every review moderation write by role', function (string $method, Closure $target, array $allowed, string $role) {
    RoleMatrix::check($this, $role, $method, $target, $allowed);
})->with('policy review writes')->with(RoleMatrix::ROLES);
