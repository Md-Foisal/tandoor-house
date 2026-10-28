<?php

namespace App\Enums;

/**
 * The 14 major allergens UK food businesses must declare (FSA).
 */
enum Allergen: string
{
    case Celery = 'celery';
    case Gluten = 'gluten';
    case Crustaceans = 'crustaceans';
    case Eggs = 'eggs';
    case Fish = 'fish';
    case Lupin = 'lupin';
    case Milk = 'milk';
    case Molluscs = 'molluscs';
    case Mustard = 'mustard';
    case TreeNuts = 'tree_nuts';
    case Peanuts = 'peanuts';
    case Sesame = 'sesame';
    case Soya = 'soya';
    case Sulphites = 'sulphites';

    public function label(): string
    {
        return __('allergens.'.$this->value);
    }
}
