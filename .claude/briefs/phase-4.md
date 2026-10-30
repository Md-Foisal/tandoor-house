# Phase 4 builder brief: backend wiring

Read first: `CLAUDE.md`, `.claude/agents/screen-builder.md`, `.claude/briefs/staff-screens.md` (conventions, states, checks still apply), `docs/plan.md` §5–6 and `docs/decisions.md`. Phase 4 turns the working screens into a real backend: payments, real-time updates, notifications, complete policies.

## Foundations built by the main session (use, do not edit)
- **Payments** (`app/Payments/`):
  - `PaymentGateway` interface. `start(Payable $for, int $amount, PaymentMethod $method, array $context): PaymentAttempt` returns `status` (succeeded | requires_action | pending | failed), `reference`, `clientSecret` (Stripe) and `message`. `refund(Payment $payment, int $amount, string $reason): RefundResult`. `name()`.
  - Drivers: `FakeGateway` (default in local and tests; card amounts ending in 51 pence are declined so failure paths can be tested), `VenueGateway` (pay at venue / cash: pending until staff take it) and `StripeGateway` (test mode; used only when `STRIPE_SECRET` is set).
  - `Payments` service: resolves the driver per method and records `payments` rows. Config is in `config/payments.php`.
  - Webhooks: `POST /webhooks/payments/{driver}`.
- **Events** (`app/Events/`), dispatched after commit by `OrderStateMachine`, checkout and table requests: `OrderPlaced`, `OrderStatusChanged` (from, to), `KitchenLineUpdated`, `TableRequestRaised`, `ReservationUpdated`.
  - Each broadcasts on private channels with names from `App\Broadcasting\Channels` (`staff.orders`, `staff.kitchen`, `staff.floor`, `staff.reservations`, `staff.deliveries.{riderId}`, `orders.{number}` for the signed-in owner).
  - Channel auth is in `routes/channels.php`, using the `StaffArea` gates.
- **Real-time front end**: `resources/js/lib/realtime.ts`.
  - `useChannel(name, events, onEvent)` subscribes through Laravel Echo and Reverb.
  - `useLive(reloadProps, { channel, events, fallbackMs })` reloads the given Inertia props when an event arrives. It falls back to polling when Echo is not connected (no Reverb server running, offline, guests).
- **Notifications**:
  - `App\Notifications\Channels\SmsChannel` with an `SmsSender` interface; the `LogSmsSender` driver writes to the log.
  - `WhatsAppChannel` and `WhatsAppSender`, with a null driver that is off.
  - Customers are notified on demand (`Notification::route('mail', ...)->route('sms', ...)`) because guests have no account.
- **`App\Support\ActivityRecorder`**, `App\Support\Money`, `App\Support\CodeGenerator`, `App\Support\Csv` as before.

## Rules
- Money in pence; never trust amounts from the browser — recompute on the server.
- Every payment state change is idempotent (webhooks can repeat): key on the gateway reference.
- Card details never touch our server: Stripe Elements in the browser with a client secret; the fake gateway uses a clearly labelled test form (test card numbers only, never stored).
- Queued work implements `ShouldQueue`; tests use `Queue::fake()` / `Notification::fake()` / `Event::fake()` where it keeps them fast, plus at least one test per feature that runs the real path synchronously.
- Same checks as before; with many agents running, follow `.claude/briefs/review-fixes.md` → "When many fix agents run at once" (no builds, no migrate:fresh, only your own tests).
