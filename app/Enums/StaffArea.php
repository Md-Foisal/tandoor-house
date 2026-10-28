<?php

namespace App\Enums;

/**
 * Sections of the staff panel and the roles that may use each. Gates named
 * "staff.<area>" and the sidebar both read from here.
 */
enum StaffArea: string
{
    case Today = 'today';
    case Orders = 'orders';
    case Kitchen = 'kitchen';
    case Floor = 'floor';
    case Reservations = 'reservations';
    case Deliveries = 'deliveries';
    case Menu = 'menu';
    case Inventory = 'inventory';
    case Team = 'team';
    case Reports = 'reports';
    case Reviews = 'reviews';
    case Settings = 'settings';
    case Activity = 'activity';

    /**
     * @return list<Role>
     */
    public function roles(): array
    {
        return match ($this) {
            self::Today => [Role::Waiter, Role::Cashier, Role::Kitchen, Role::Rider, Role::Manager, Role::Owner],
            self::Orders => [Role::Cashier, Role::Waiter, Role::Manager, Role::Owner],
            self::Kitchen => [Role::Kitchen, Role::Manager, Role::Owner],
            self::Floor, self::Reservations => [Role::Waiter, Role::Cashier, Role::Manager, Role::Owner],
            self::Deliveries => [Role::Rider, Role::Manager, Role::Owner],
            self::Inventory => [Role::Kitchen, Role::Manager, Role::Owner],
            self::Menu, self::Team, self::Reports, self::Reviews, self::Activity => [Role::Manager, Role::Owner],
            self::Settings => [Role::Owner, Role::Manager],
        };
    }

    public function allows(Role $role): bool
    {
        return in_array($role, $this->roles(), true);
    }

    /**
     * @return list<self>
     */
    public static function for(Role $role): array
    {
        return array_values(array_filter(self::cases(), fn (self $area) => $area->allows($role)));
    }
}
