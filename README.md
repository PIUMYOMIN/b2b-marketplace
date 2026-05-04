# Pyonea Backend (Laravel API)

This is the backend API for the Pyonea B2B marketplace.  
It powers buyer, seller, and admin workflows for catalog, orders, checkout, payments, RFQ, delivery, moderation, and reporting.

Base API namespace: `/api/v1`

## 1) Tech stack

- Laravel 12
- PHP 8.2+
- Sanctum auth + role middleware (Spatie Permission)
- MySQL/SQLite (configurable)
- Queue workers for async jobs
- Optional integrations for MMQR / KBZ Pay / Wave Pay

## 2) Core architecture

Main application modules:

- Authentication and user profile
- Seller onboarding and verification
- Product catalog, categories, options, variants
- Cart, checkout OTP, orders, and payments
- Delivery operations (seller, platform, courier, tracking)
- RFQ (request-for-quotation) between buyers and sellers
- Reviews, follows, wishlist
- Admin analytics, moderation, reports, finance

Primary folders:

- `app/Http/Controllers/Api/` - API controllers
- `app/Models/` - Eloquent models
- `routes/api.php` - route registry
- `database/migrations/` - schema
- `database/seeders/` - initial/reference data
- `config/` - environment-driven settings

## 3) Environment configuration

Create `.env` from `.env.example`.

Important keys:

- App:
  - `APP_NAME`, `APP_ENV`, `APP_KEY`, `APP_URL`, `APP_DEBUG`, `APP_TIMEZONE`
- Frontend/cross-origin:
  - `APP_FRONTEND_URL`
  - `CORS_SUPPORTS_CREDENTIALS`
- Database:
  - `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- Session / queue / cache:
  - `SESSION_*`, `QUEUE_CONNECTION`, `CACHE_STORE`
- Mail:
  - `MAIL_*`
- Payments and verification:
  - `MMQR_*`, `KBZPAY_*`, `WAVEPAY_*`
  - `RECAPTCHA_SITE_KEY`, `RECAPTCHA_SECRET_KEY`
- Optional:
  - `ELASTICSEARCH_HOST`

Generate key after first setup:

```bash
php artisan key:generate
```

## 4) Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan storage:link
php artisan serve
```

If you use Vite assets from Laravel context:

```bash
npm install
npm run dev
```

## 5) Running workers and logs

Queue worker:

```bash
php artisan queue:listen --tries=1
```

Logs:

```bash
php artisan pail
```

Composer helper script exists:

```bash
composer run dev
```

## 6) API auth and roles

Authentication:

- Register/login endpoints issue auth context.
- Protected routes use `auth:sanctum`.

Role-based access:

- `role:admin`
- `role:seller`
- `role:buyer`
- some endpoints allow combined roles (`role:buyer|admin`, etc.)

## 7) Route domains (high-level)

All in `routes/api.php` under `/api/v1`.

Public:

- auth login/register
- password reset
- contact/report submissions
- newsletter subscribe/confirm/unsubscribe
- announcements
- checkout location lookup
- seller/product/category browsing
- order tracking by order number
- payment methods list

Authenticated:

- user profile and account operations
- notifications
- wishlist/follow
- buyer cart/coupon validation
- orders and checkout fees
- payments and history
- deliveries and tracking updates
- RFQ send/receive/quote flow

Admin:

- seller verification and status management
- product/review moderation
- commission rules
- delivery fee and COD invoice management
- analytics and exports
- contact/report admin queues

Seller:

- onboarding steps and document upload
- store settings/policies/business hours
- products/options/variants/images/discounts/coupons
- delivery area configuration
- wallet and COD invoice submission

## 8) Delivery and fee management (important)

Delivery model supports:

- method: `supplier` or `platform`
- lifecycle status: pickup -> transit -> delivered/failed/etc.
- platform fee fields:
  - `platform_delivery_fee`
  - `delivery_fee_status` (`not_applicable`, `outstanding`, `collected`)
  - collection metadata (`delivery_fee_collected_at`, `delivery_fee_collection_ref`, etc.)

Admin operations include:

- list platform delivery fees
- mark fee collected
- confirm fee submissions
- adjust platform fee quote before collection

New adjustment endpoint:

- `PATCH /api/v1/admin/deliveries/{id}/platform-fee`
- Constraints:
  - admin only
  - platform delivery only
  - blocked after fee marked collected
- Creates an internal tracking update note for auditability.

## 9) Checkout, commission, and visibility policy

- Checkout fees are resolved server-side (`/orders/checkout-fees`) using shipping-zone and commission/tax logic.
- Buyer-facing UI can present combined shipping/handling without exposing internal platform settlement details.
- Commission and settlement details are still persisted for seller/platform financial reporting.

## 10) RFQ flow summary

- Buyers create RFQs with item/specification requirements.
- Sellers submit quotes per RFQ.
- Buyers accept/reject quotes.
- Read endpoints are scoped to sent/received plus record-level auth checks.

## 11) Testing and quality

Run test suite:

```bash
php artisan test
```

Useful checks:

```bash
php -l app/Http/Controllers/Api/SomeController.php
```

Formatting/linting:

- Laravel Pint is available in dev dependencies.

## 12) Deployment checklist

- Set `APP_ENV=production`, `APP_DEBUG=false`
- Use strong DB credentials and restricted CORS origins
- Configure queue workers and process manager (Supervisor/systemd)
- Run migrations safely on deploy
- Configure storage symlink and writable directories
- Configure payment and webhook secrets
- Ensure HTTPS and secure cookie/session settings

## 13) Troubleshooting

- 401/419 auth issues:
  - validate token/session handling and frontend credentials mode
  - check sanctum + CORS settings
- CORS failures:
  - verify `APP_FRONTEND_URL` and CORS config
- Missing uploaded files:
  - ensure `storage:link` exists and filesystem permissions are correct
- Queue-backed tasks not running:
  - confirm queue worker is online and using same `.env`
