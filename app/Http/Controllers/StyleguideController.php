<?php

namespace App\Http\Controllers;

use App\Http\Resources\MenuItemResource;
use App\Models\MenuItem;
use Inertia\Inertia;
use Inertia\Response;

class StyleguideController extends Controller
{
    public function index(): Response
    {
        $items = MenuItem::with(['variants', 'modifierGroups.modifiers'])
            ->whereIn('slug', ['chicken-tikka', 'chana-masala', 'lamb-seekh-kebab', 'goan-prawn-curry'])
            ->get();

        $soldOut = $items->firstWhere('slug', 'goan-prawn-curry');
        $soldOut?->setAttribute('is_available', false);

        return Inertia::render('styleguide/index', [
            'items' => MenuItemResource::collection($items),
        ]);
    }

    public function motion(): Response
    {
        $items = MenuItem::with('variants')
            ->where('is_special', true)
            ->orWhereIn('slug', ['butter-chicken', 'house-black-daal'])
            ->limit(6)
            ->get();

        return Inertia::render('styleguide/motion', [
            'items' => MenuItemResource::collection($items),
        ]);
    }
}
