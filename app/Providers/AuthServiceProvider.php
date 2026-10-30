<?php

namespace App\Providers;

use App\Models\ActivityLog;
use App\Models\Attendance;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\Ingredient;
use App\Models\MenuItem;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\Review;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\Supplier;
use App\Models\TableRequest;
use App\Models\TipAllocation;
use App\Models\User;
use App\Policies\ActivityLogPolicy;
use App\Policies\AttendancePolicy;
use App\Policies\CategoryPolicy;
use App\Policies\DeliveryPolicy;
use App\Policies\DiningTablePolicy;
use App\Policies\IngredientPolicy;
use App\Policies\MenuItemPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\PurchaseOrderPolicy;
use App\Policies\ReviewPolicy;
use App\Policies\SettingPolicy;
use App\Policies\ShiftPolicy;
use App\Policies\Staff\OrderBoardPolicy;
use App\Policies\Staff\ReservationBoardPolicy;
use App\Policies\SupplierPolicy;
use App\Policies\TableRequestPolicy;
use App\Policies\TipAllocationPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Model policies for the staff panel, on top of the "staff.<area>" gates
 * that guard each section's routes. Order and Reservation keep their
 * customer-facing policies, so their staff rules are named abilities.
 */
class AuthServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    private const POLICIES = [
        ActivityLog::class => ActivityLogPolicy::class,
        Attendance::class => AttendancePolicy::class,
        Category::class => CategoryPolicy::class,
        DiningTable::class => DiningTablePolicy::class,
        Ingredient::class => IngredientPolicy::class,
        MenuItem::class => MenuItemPolicy::class,
        Payment::class => PaymentPolicy::class,
        PurchaseOrder::class => PurchaseOrderPolicy::class,
        Review::class => ReviewPolicy::class,
        Setting::class => SettingPolicy::class,
        Shift::class => ShiftPolicy::class,
        Supplier::class => SupplierPolicy::class,
        TableRequest::class => TableRequestPolicy::class,
        TipAllocation::class => TipAllocationPolicy::class,
        User::class => UserPolicy::class,
    ];

    /** @var array<string, array{class-string, string}> */
    private const ABILITIES = [
        'staff.orders.move' => [OrderBoardPolicy::class, 'move'],
        'staff.orders.edit' => [OrderBoardPolicy::class, 'edit'],
        'staff.orders.receipt' => [OrderBoardPolicy::class, 'receipt'],
        'staff.orders.assign-rider' => [OrderBoardPolicy::class, 'assignRider'],
        'staff.kitchen.cook' => [OrderBoardPolicy::class, 'cook'],
        'staff.deliveries.act' => [DeliveryPolicy::class, 'act'],
        'staff.deliveries.assign' => [DeliveryPolicy::class, 'assign'],
        'staff.reservations.book' => [ReservationBoardPolicy::class, 'book'],
        'staff.reservations.transition' => [ReservationBoardPolicy::class, 'transition'],
        'staff.reservations.seat' => [ReservationBoardPolicy::class, 'seat'],
        'staff.reservations.reassign' => [ReservationBoardPolicy::class, 'reassignTable'],
    ];

    public function boot(): void
    {
        foreach (self::POLICIES as $model => $policy) {
            Gate::policy($model, $policy);
        }

        foreach (self::ABILITIES as $ability => $callback) {
            Gate::define($ability, $callback);
        }
    }
}
