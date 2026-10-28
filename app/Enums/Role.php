<?php

namespace App\Enums;

enum Role: string
{
    case Customer = 'customer';
    case Waiter = 'waiter';
    case Cashier = 'cashier';
    case Kitchen = 'kitchen';
    case Rider = 'rider';
    case Manager = 'manager';
    case Owner = 'owner';

    public function isStaff(): bool
    {
        return $this !== self::Customer;
    }

    /**
     * Managers and owners run the business side: menu, stock, people, reports.
     */
    public function isManagement(): bool
    {
        return in_array($this, [self::Manager, self::Owner], true);
    }

    public function label(): string
    {
        return __('roles.'.$this->value);
    }
}
