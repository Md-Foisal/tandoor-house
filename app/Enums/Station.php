<?php

namespace App\Enums;

/**
 * Kitchen sections an order line is routed to.
 */
enum Station: string
{
    case Tandoor = 'tandoor';
    case Curry = 'curry';
    case Tawa = 'tawa';
    case Cold = 'cold';
    case Bar = 'bar';

    public function label(): string
    {
        return __('stations.'.$this->value);
    }
}
