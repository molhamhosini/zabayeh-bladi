# ذبائح بلادي (Zabayeh Bladi)

Marketplace for ordering slaughtered livestock in the UAE: Zabayeh Bladi's own stock plus contracted butcher shops, each branch with its own portal.

| Folder | What |
|---|---|
| `backend/` | Laravel 13 API (`/api/v1`), admin panel (`/admin`) and branch portal (`/branch`) built with Filament 5 |
| `app/` | Flutter customer app (Android, iOS), Arabic first with English |
| `brand/` | Logo files and colours |

## Backend

```sh
cd backend
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed       # emirates, areas, categories, preparation options, a Dubai branch, demo slots
php artisan storage:link
php artisan serve
php artisan test
```

Set in `.env`: `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`, `ADMIN_EMAIL`, `ADMIN_PASSWORD`.
Stripe webhook URL: `https://<domain>/api/v1/webhooks/stripe` (events: `payment_intent.succeeded`, `payment_intent.payment_failed`, `charge.refunded`).

SMS codes are written to the log until an SMS provider is connected (`app/Services/SmsSender.php`).

## App

```sh
cd app
flutter pub get
flutter run --dart-define=API_URL=https://<domain>/api/v1 --dart-define=STRIPE_KEY=pk_live_...
```

## Brand

Maroon `#8C1C2B`, dark maroon `#4A0E17`, gold `#D4AF6A`, cream `#F7F1E6`, font Tajawal.
