# Phase 3 review fixes — shared rules

You fix review findings in Tandoor House (the project root). Read `CLAUDE.md` and `.claude/briefs/staff-screens.md` first. Three other fix agents work in parallel on other files: touch ONLY the files of your area listed in your task. Do not commit.

Already done by the main session (use, do not redo):
- `App\Services\OrderStateMachine::transition()` now locks the order row (`lockForUpdate`) inside its transaction and re-checks the allowed transition there.
- `App\Support\ActivityRecorder` (inject it): `record(string $event, string $description, ?Model $subject = null, array $properties = [], ?User $by = null, bool $automated = false)`. Replace your area's own activity-log writer(s) with it and delete the old helper class if nothing else uses it (grep first; if another area still uses it, leave it and say so).
- `App\Support\Csv` (moved from App\Actions\Team\Csv): use it for any CSV download in your area.

General rules for every fix:
- Concurrency: any check-then-write that money, stock, seating or status depends on must re-read the row(s) with `lockForUpdate()` INSIDE a `DB::transaction` and re-check there. Use `->whereKey($id)->firstOrFail()` (not `findOrFail` on a query builder) to keep PHPStan types precise.
- Form Requests in your area: `authorize()` must return `$this->user()?->can('staff.<area>') ?? false` (defence in depth), plus any per-action rule.
- Activity descriptions go through `__()` with keys in your area's lang file (no hard-coded English in PHP descriptions).
- Policies you use: resolve via the container (constructor/method injection), not `new`.
- Add or adjust Pest tests for every behaviour you change (including a role test for every new permission rule). Concurrency can be tested by asserting the lock path (e.g. the second request after state changed is refused) rather than real parallelism.
- Checks: `vendor/bin/pint <your paths>`, `vendor/bin/phpstan analyse --memory-limit=1G` (no errors in your files), `php artisan test --compact` (whole suite green), `npm run types:check`, `npx vp fmt <your paths>` then `npx vp check`, `npm run build` if you touched TSX.
- Return a summary under 150 words: what you fixed, what you decided, what you did not fix and why.

---

# The four fix tasks (Phase 3 review, 33 findings)

Finding 4 (order row lock in `OrderStateMachine::transition`) and the shared `ActivityRecorder` / `Support\Csv` are already done and committed. Give each task below to its own `screen-builder` subagent, all four in parallel; prefix each with: "Read .claude/briefs/review-fixes.md (top part) first and follow it."

## Task A — orders + kitchen
Files: routes/staff/orders.php, routes/staff/kitchen.php, app/Http/Controllers/Staff/Orders/**, app/Http/Requests/Staff/Orders/**, app/Policies/Staff/OrderBoardPolicy.php, app/Http/Controllers/Staff/KitchenController.php, app/Http/Requests/Staff/Kitchen/**, resources/js/pages/staff/{orders,kitchen}/**, resources/js/components/{staff-orders,kitchen}/**, lang/en_GB/staff_{orders,kitchen}.php, tests/Feature/Staff/{OrderBoardTest,KitchenTest}.php. Never Deliveries files.
1. Refund limit race: lock order + payment rows in the transaction, re-read refunded total, re-run the cashier £50-per-order limit there.
2. Refund by lines never above what was charged (unit_price includes modifiers — verify; pro-rate the order discount), capped at the remaining refundable amount.
3. Per-transition role map in the policy. Waiters: accept, start cooking, ready, served, collected, complete dine-in/pickup; not reject/cancel (cashier, manager, owner); not out_for_delivery/delivered from the board (riders via deliveries, or managers/owners; out_for_delivery needs an assigned rider). Cashiers: all except delivery steps. Test each role.
5. Order edits: re-check editability under lock; when a paid order shrinks, record the difference through the refund path (preferred) or block with a message — never toast an unrecorded refund.
19. Board: search in SQL (number, contact name/phone, table label) before limiting; active columns oldest first, Done newest first and only Done capped.
23. bumpOrder: load the order once, bump the station's lines in one go; all order status changes still through `OrderStateMachine`.
24/25. Replace hard-coded 'en-GB'/'Europe/London'/toLocaleTimeString with one formatter (constants defined once); remove `eslint-disable react-hooks/exhaustive-deps` by fixing dependencies; no text under 12px.
27. Replace OrderActivity (and kitchen logging) with ActivityRecorder.
28. Assigning riders from the board: managers/owners only (same rule as deliveries); reuse `App\Actions\Deliveries\AssignRider` if Task B has created it, else implement the rule and note the duplication.
32. Remove the "Phase 4" narration in RefundOrder's docblock.
33. Inject OrderBoardPolicy instead of `new`.
15. Form Request `authorize()` for these areas.

## Task B — deliveries + inventory
Files: routes/staff/{deliveries,inventory}.php, app/Http/Controllers/Staff/DeliveryController.php, app/Http/Controllers/Staff/{Deliveries,Inventory}/**, app/Http/Requests/Staff/{Deliveries,Inventory}/**, app/Policies/{DeliveryPolicy,InventoryPolicy}.php, app/Actions/{Deliveries,Inventory}/**, resources/js/pages/staff/{deliveries,inventory}/**, resources/js/components/{deliveries,staff-inventory}/**, lang/en_GB/staff_{deliveries,inventory}.php, tests/Feature/Staff/{DeliveryTest,InventoryTest}.php, one new migration database/migrations/2026_10_29_100000_*.php if needed.
6. DeliverController: payment `amount` = order total; cash tendered in `orders.cash_collected`, change in payment `meta`; a rider cannot mark a cash order paid while short (test); lock the order.
7. Suppliers, purchase orders (list/show/PDF) and recipe costs/margins are manager/owner only; kitchen keeps ingredients, stock-in, waste, movements (and receiving sent POs only if costs stay hidden — or drop kitchen receiving; justify). Hide tabs the role cannot open.
8. PurchaseOrders send/cancel/replaceLines/receive: lock the PO row in a transaction and re-check status; references via `App\Support\CodeGenerator`, unique index if missing.
22. Aggregate queries instead of whole-table loads in WasteController/IngredientController; paginate the waste list.
26. Screenshot every inventory tab as manager (and the stock page as kitchen); view the 375-light contact sheets.
27. Replace DeliveryActivity and InventoryActivity with ActivityRecorder.
28. Create `App\Actions\Deliveries\AssignRider` (managers/owners; rider role; delivery in ready/out_for_delivery) and use it in AssignController.
33. Inject DeliveryPolicy and InventoryPolicy instead of `new`.
15. Form Request `authorize()` for these areas.

## Task C — floor + reservations + team + clock
Files: routes/staff/{floor,reservations,team}.php, app/Http/Controllers/Staff/{FloorController.php,Floor/**,ReservationBoardController.php,Reservations/**,Team/**,Today/ClockController.php}, app/Http/Requests/Staff/{Floor,Reservations,Team}/**, app/Actions/{Floor,Team}/**, resources/js/pages/staff/{floor,reservations,team}/**, resources/js/components/{floor,staff-reservations,staff-team}/**, lang/en_GB/staff_{floor,reservations,team,today}.php, tests/Feature/Staff/{FloorTest,ReservationBoardTest,TeamTest,TodayTest}.php, app/Models/DiningTable.php (only for the new columns), one migration database/migrations/2026_10_29_110000_*.php.
11. Confirming a pending-deposit booking (deposit paid) is managers/owners only and needs a note or payment reference; logged.
12. One seat path: the reservations "seat" action delegates to `App\Actions\Floor\SeatReservation`.
13. SeatReservation, MergeTables, TransferTable, ChangeTableStatus: lock tables (and reservation) in the transaction and re-check there.
14. ReassignTableController: reload the reservation under lock, eager-load its table, re-check status and overlap inside the transaction.
9. UpdateStaffMember: change `phone` only when the key is present.
17. SaveShift and CopyWeek in transactions; managers roster only non-owner/non-manager staff (and themselves); owners anyone. Test.
18. ClockController: atomic clock-in/out (lock, re-check).
27. Replace RecordFloorActivity, ReservationActions::log, TeamActivity and the clock logger with ActivityRecorder; descriptions via `__()`.
15. Form Request `authorize()` for these areas.
Plus: add nullable `party_size` and `seated_at` to `dining_tables`; set them when seating a walk-in or booking, clear when freed, show on the map, update the model's fillable and @property docblock.

## Task D — menu + settings + reviews + activity + reports
Files: routes/staff/{menu,settings,reviews,activity,reports}.php, app/Http/Controllers/Staff/{Menu,Settings,Reviews,Reports,Activity}/**, app/Http/Controllers/Staff/ActivityLogController.php, app/Http/Requests/Staff/{Menu,Settings,Reviews,Reports,Activity}/**, app/Actions/Menu/**, app/Reports/**, resources/js/pages/staff/{menu,settings,reviews,activity,reports}/**, resources/js/components/{staff-menu,staff-settings,staff-reviews,staff-activity,staff-reports}/**, lang/en_GB/staff_{menu,settings,reviews,activity,reports}.php, tests/Feature/Staff/{MenuManagementTest,SettingsTest,ReviewModerationTest,ActivityLogTest,ReportsTest}.php.
16. SaveMenuItem: reject variant/modifier ids that are not the item's (scoped exists rules + action check); one transaction.
10. Deleting or deactivating a delivery zone is owner only. Test manager 403.
29. All activity descriptions in this area through `__()`.
30. Use the menu cache-key constant instead of the literal 'menu.v1'.
31. Setting writes and their log entry in one transaction.
20. Reports: refunds reduce sales only for the food/drink/delivery share; tip/service refunds reported separately; refunds counted in the period they were made (`payments.meta.refunds` timestamps, else `updated_at`); one explanatory line on the screen; tests.
21. ReportCsv reuses `App\Support\Csv::download` (keep BOM and the anti-formula guard).
25. No chart text under 12px; hour bucketing driver-aware (`DATE_FORMAT(placed_at, '%Y-%m-%d %H')` on MySQL, `strftime('%Y-%m-%d %H', placed_at)` on SQLite via `DB::getDriverName()`).
27. Replace ReviewQueue::log, SettingsChange::log, RecordMenuChange with ActivityRecorder.
15. Form Request `authorize()` for these areas.

---

## When many fix agents run at once
Up to nine fix agents may run together. To keep each other's checks reliable:
- Do NOT run `npm run build`, `php artisan migrate:fresh`, or the screenshot script; the main session does those centrally.
- Run only your own test files (`php artisan test --compact tests/Feature/Staff/<YourTest>.php ...`), `vendor/bin/pint <your paths>`, `vendor/bin/phpstan analyse --memory-limit=1G <your paths>`, `npx vp fmt <your TSX paths>` and `npm run types:check` (report, don't fix, type errors in others' files).
- If a test of yours fails only with "Vite manifest" / "Not a valid Inertia response" because a page is missing from the last build, say so; the main session rebuilds.
