<?php

use App\Models\Category;
use App\Models\MenuItem;
use Tests\Feature\Policies\RoleMatrix;

dataset('policy menu writes', [
    'add a category' => ['post', fn () => '/staff/menu/categories', RoleMatrix::MANAGEMENT],
    'rename a category' => ['patch', fn () => '/staff/menu/categories/'.Category::factory()->create()->id, RoleMatrix::MANAGEMENT],
    'delete a category' => ['delete', fn () => '/staff/menu/categories/'.Category::factory()->create()->id, RoleMatrix::MANAGEMENT],
    'move a category' => ['post', fn () => '/staff/menu/categories/'.Category::factory()->create()->id.'/move', RoleMatrix::MANAGEMENT],
    'add a dish' => ['post', fn () => '/staff/menu/items', RoleMatrix::MANAGEMENT],
    'edit a dish' => ['put', fn () => '/staff/menu/items/'.MenuItem::factory()->create()->id, RoleMatrix::MANAGEMENT],
    'change a dish VAT rate' => ['put', fn () => ['/staff/menu/items/'.MenuItem::factory()->create(['vat_rate' => 2000])->id, ['vat_rate' => 0]], RoleMatrix::OWNER],
    'toggle availability' => ['patch', fn () => '/staff/menu/items/'.MenuItem::factory()->create()->id.'/availability', RoleMatrix::MANAGEMENT],
    'toggle special' => ['patch', fn () => '/staff/menu/items/'.MenuItem::factory()->create()->id.'/special', RoleMatrix::MANAGEMENT],
]);

it('guards every menu write by role', function (string $method, Closure $target, array $allowed, string $role) {
    RoleMatrix::check($this, $role, $method, $target, $allowed);
})->with('policy menu writes')->with(RoleMatrix::ROLES);
