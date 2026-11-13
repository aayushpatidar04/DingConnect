# DingConnect Mobile API

This reference describes the application API under `/api`. It is intended for mobile clients and uses Laravel Sanctum personal access tokens.

> **NEW changes — APIs added since baseline `e16d8f6efa17fca87d88a334455c2814b717ae89`:** retailer commissions (`GET /api/retailer/commissions`), offer eligibility (`GET /api/retailer/offers/eligible`), and offers on the retailer dashboard/recharge; admin analytics (`GET /api/admin/analytics`), commission levels/rates, retailer level assignment/history, commission reports/export, offer/redemption management, itemized SIM orders with dynamic product management and bulk retailer limits (default limit 500), and **SIM provider-file comparisons** (multiple providers and normalized matching). These newest changes are marked in their sections below and in the Postman collection. Each request includes a saved response-format example.
>
> Historical retailer-connection backfill is intentionally an internal Artisan command, not a public API endpoint.

## Getting started

Set the Postman `base_url` variable to the application origin, without a trailing slash (for example `https://example.com`). The API prefix `/api` is included in every request URL.

Send JSON requests with:

```http
Accept: application/json
Content-Type: application/json
```

For protected routes, send:

```http
Authorization: Bearer <access_token>
```

Registration and OTP verification responses contain `data.access_token`. Store it securely on the device. Logout revokes only the current token. Changing/resetting the password revokes all of the user's tokens.

## Login and account access

1. Register with `POST /api/auth/register` or submit email/password to `POST /api/auth/login`.
2. Login sends a six-digit OTP to the registered email address; submit it to `POST /api/auth/verify-otp`.
3. Use the returned bearer token for protected API requests.
4. A registered retailer starts with `kyc_status: pending`. They can authenticate and view their profile, but retailer dashboard, wallet, recharge, and transaction APIs require approved KYC.
5. Admin API access requires an admin role. Retailer APIs require a retailer role, an active account, and approved KYC.

Login OTPs expire after five minutes and allow up to five attempts. Resend is available after 60 seconds. All `/api/auth/*` endpoints are limited to 10 requests per minute per client IP.

## Response and error conventions

Responses are JSON unless the endpoint explicitly downloads a file. Most successful endpoints use `success: true`, with a `data` object or array. Paginated results use Laravel's paginator structure, including `current_page`, `data`, `last_page`, and `per_page`.

Common status codes:

| Status | Meaning |
|---|---|
| `200` | Request succeeded |
| `201` | Resource created or registration completed |
| `202` | Recharge accepted for background processing |
| `401` | Missing/invalid bearer token or invalid login credentials |
| `403` | Account inactive, role not authorized, or retailer KYC not approved |
| `404` | Resource not found (including a retailer accessing another retailer's transaction) |
| `409` | Conflicting state, such as processing KYC twice or refunding an ineligible transaction |
| `422` | Validation or business-rule failure; Laravel returns `message` and `errors` |
| `400` | External provider/payment operation failed |

Never treat `202` as a completed recharge: poll the transaction status endpoint. `success` in response bodies and the transaction's `status` are separate values.

## Auth

### `POST /api/auth/register`

Creates an active retailer with pending KYC and an initial wallet. Also issues a token immediately.

| Field | Requirement |
|---|---|
| `name` | Required string, max 255 |
| `email` | Required valid, unique email |
| `phone` | Required string, max 20, unique |
| `password` | Required string, at least 8 characters |
| `password_confirmation` | Must match password |
| `shop_name`, `address`, `city`, `county`, `postcode`, `vat_number`, `company_reg_number`, `utr_number` | Optional retailer details |

Returns `201`; token/user are in `data`.

### `POST /api/auth/login`

Body: `{ "email": "retailer@example.com", "password": "secret" }`. If credentials are correct, sends an OTP and returns `data.requires_otp: true`. Does not issue a token.

### `POST /api/auth/verify-otp`

Body: `{ "email": "retailer@example.com", "otp": "123456" }`. Returns `data.user`, `data.access_token`, and `data.token_type: "Bearer"`.

### `POST /api/auth/resend-otp`

Body: `{ "email": "retailer@example.com" }`. Requires an active OTP login session and the 60-second resend interval.

### `POST /api/auth/forgot-password`

Body: `{ "email": "retailer@example.com" }`. Requests a password-reset link by email.

### `POST /api/auth/reset-password`

Body: `{ "token": "<reset-token>", "email": "retailer@example.com", "password": "new-secret", "password_confirmation": "new-secret" }`. A successful reset revokes all previously issued tokens.

### `POST /api/auth/logout` — bearer token required

Revokes the current Sanctum token. Response: `{ "success": true, "message": "Logged out successfully." }`.

### `GET /api/auth/me` — bearer token and active account required

Returns the current user, role(s), KYC/account status, and wallet when available.

## Public lookups

| Method and path | Parameters | Result |
|---|---|---|
| `GET /api/countries` | None | Active countries (`id`, `name`, `iso_code`, `calling_code`, `currency`, `flag_emoji`) |
| `GET /api/countries/{iso}/operators` | Path `iso`, e.g. `GB` | Active operators configured for that country |
| `GET /api/operators/search` | Optional query `q`, `country_id` | Active operator matches |

## Profile and password — bearer token and active account required

### `GET /api/profile`

Returns the signed-in user's profile and role/KYC/account fields. `GET /api/retailer/profile` is the same handler but also requires the retailer role and approved KYC.

### `PUT /api/profile` or `PUT /api/retailer/profile`

Requires `name`, `email`, and `phone`. Accepts optional `shop_name`, `address`, `city`, `county`, `postcode`, `vat_number`, `company_reg_number`, and `utr_number`. Email and phone must remain unique. To change the password as part of this update, include `current_password`, `new_password`, and `new_password_confirmation` (minimum 8 characters).

### `PUT /api/password`

Body: `{ "current_password": "...", "password": "...", "password_confirmation": "..." }`. A successful update revokes all tokens; sign in again.

## Retailer dashboard and wallet

All routes in this section require bearer authentication, an active account, retailer role, and approved KYC.

| Method and path | Query/body | Description |
|---|---|---|
| `GET /api/retailer/dashboard` | None | Wallet/available/locked balance, transaction stats, recent transactions, and 7-day chart |
| `GET /api/retailer/wallet` | Optional `status` (`pending`, `completed`, `failed`), `from`, `to` dates | Wallet, available balance, and paginated top-ups (20 per page) |
| `GET /api/retailer/wallet/topup` | None | Wallet, available balance, Stripe publishable key |
| `POST /api/retailer/wallet/topup` | JSON `amount` | Creates a Stripe PaymentIntent; see below |
| `GET /api/retailer/wallet/ledger` | None | Paginated wallet ledger (50 per page) |

Top-up body: `{ "amount": 25 }`. Amount must be between the configured minimum and maximum recharge values. Response includes `client_secret`, `payment_intent_id`, `amount` (total charged), `currency`, `stripe_publishable_key`, and `topup_id`. The app must complete the payment with Stripe's client SDK; the API response alone does not credit the wallet.

## Recharge flow

Every endpoint in this section requires bearer authentication, an active retailer role, and approved KYC. Provider/catalog/pricing lookups contact DingConnect and/or Valuetopup services; results and availability depend on provider configuration.

| Method and path | Required parameters | Optional parameters / notes |
|---|---|---|
| `GET /api/retailer/recharge/operators` | `phone_number` (7–20 chars) | `country_iso` (2 chars). Looks up operators for the number; phone digits are normalized. |
| `GET /api/retailer/recharge/providers` | `country_iso` (2 chars) | DingConnect providers for the country |
| `GET /api/retailer/recharge/valuetopup/operators` | `country_iso` (2 chars) | Valuetopup operators, matched/merged with known operators |
| `GET /api/retailer/recharge/provider-status` | `provider_code` | DingConnect provider status |
| `GET /api/retailer/recharge/products` | `country_iso`, `provider_code` | Optional integer `valuetopup_id`; returns Ding and Valuetopup products (Ding product denominations take precedence when duplicated). |
| `GET /api/retailer/recharge/promotions` | None | Optional `country_isos`, `provider_codes` |
| `GET /api/retailer/recharge/pricing` | `sku_code`, `send_value` (minimum 1) | Optional `send_currency_iso` (3 chars) |
| `POST /api/retailer/recharge/estimate-cost` | JSON `sku_id`, `amount` (minimum 0.01) | Optional `currency` (defaults to GBP); Valuetopup estimate |
| `GET /api/retailer/recharge/validate-number` | `mobile_number`, `provider_code` | Optional `gateway` (`ding` or `valuetopup`, defaults to `ding`). Number must exist in the allowed-number register. |
| `GET /api/retailer/recharge/product-description` | `sku_code` | Retrieves description/read-more Markdown |
| `POST /api/retailer/recharge` | JSON described below | Creates a transaction and queues processing; returns `202` |

Number validation example:

```json
{
  "mobile_number": "447700900123",
  "provider_code": "provider-code",
  "gateway": "ding"
}
```

For phone numbers, the allowed-number register is checked first, then DingConnect checks account/provider association for the Ding gateway. Valuetopup returns after the allowed-number check. Non-phone serials are matched against the allowed-number register without a provider lookup. Success returns `account_number`, `gateway`, and `provider`; failures may return `422`.

Recharge request example:

```json
{
  "sku_code": "SKU-CODE",
  "send_value": 10,
  "send_currency": "GBP",
  "receive_value": 10,
  "receive_currency": "GBP",
  "redemption_type": "Immediate",
  "mobile_number": "447700900123",
  "country_id": 1,
  "operator_id": 1,
  "gateway": "ding",
  "provider_code": "provider-code"
}
```

`sku_code`, `send_value` (minimum 1), `redemption_type`, `country_id`, and `operator_id` are required. `redemption_type` must be `Immediate`, `ReadReceipt`, or `Manual`. `mobile_number` is required for `Immediate` and `Manual`, optional for `ReadReceipt`. `gateway` is `ding` or `valuetopup` (defaults to `ding`). Other optional product metadata accepted by the endpoint: `send_currency`, `receive_value`, `receive_currency`, `display_text`, `validity_period`, `description_markdown`, `readmore_markdown`, `benefits` (array), `product_type`, `region_code`, `provider_code`, `free_range` (boolean), `receive_value_excluding_tax`, and integer `valuetopup_sku_id`.

The request is rejected with `422` if available wallet funds are insufficient. Accepted requests return `202` and `data.transaction`; query `GET /api/retailer/transactions/{id}/status` until final status.

## Retailer transactions

| Method and path | Query/path parameters | Description |
|---|---|---|
| `GET /api/retailer/transactions` | Optional `search`, `status`, `type`, `operator_id`, `from`, `to`, `page` | Own transactions only, 25 per page. `search` checks mobile/receipt; `type` is redemption type. `to` must be on/after `from`. |
| `GET /api/retailer/transactions/{id}` | Numeric transaction ID | Own transaction details with operator and country |
| `GET /api/retailer/transactions/{id}/status` | Numeric transaction ID | Current transaction record/status |
| `GET /api/retailer/transactions/{transaction}/receipt` | Transaction ID | Downloads a PDF receipt (`application/pdf`) named from the transaction receipt number |

The legacy aliases `GET /api/transactions`, `GET /api/transactions/{id}`, `GET /api/transactions/{id}/status`, and `POST /api/transactions/topup` use the same retailer/KYC authorization and transaction behavior. Prefer the `/api/retailer/...` paths for new clients. The legacy `topup` route initiates a recharge and accepts the same payload as `POST /api/retailer/recharge`; it is not a wallet top-up.

## Admin dashboard

Admin routes require bearer authentication, an active account, and the admin role. They do not require retailer KYC.

### `GET /api/admin/dashboard`

Returns aggregate stats, top retailers, recent transactions, and chart data. Provider balances can be unavailable; inspect their `success` field.

## Admin retailer management

| Method and path | Parameters | Description |
|---|---|---|
| `GET /api/admin/retailers` | Optional `search`, `status` (`active`/`inactive`), `kyc_status` (`pending`/`approved`/`rejected`), `page` | Paginated retailers (25 per page) |
| `POST /api/admin/retailers` | JSON: `name`, `email`, `phone`, `password`, `password_confirmation`; optional retailer details | Creates active retailer with pending KYC; returns `201` |
| `GET /api/admin/retailers/{retailer}` | Retailer ID | Details, wallet, recent transactions |
| `PUT/PATCH /api/admin/retailers/{retailer}` | Requires `name`, `email`, `phone`; optional profile fields and `is_active` | Updates retailer |
| `DELETE /api/admin/retailers/{retailer}` | Retailer ID | Deactivates retailer and revokes tokens |
| `POST /api/admin/retailers/{retailer}/approve` | No body | Approves pending KYC; conflicts if already processed |
| `POST /api/admin/retailers/{retailer}/block` | No body | Toggles active state; deactivation revokes tokens |
| `POST /api/admin/retailers/{retailer}/credit` | JSON `amount` (>0), optional `description` | Credits retailer wallet |
| `POST /api/admin/retailers/{retailer}/kyc` | JSON `action` (`approve`/`reject`); `rejection_reason` required for reject | Processes KYC |
| `POST /api/admin/retailers/bulk-sim-limit` | JSON `target` (`selected` or `all`), `sim_order_limit` (integer 1–100000); `retailer_ids` required as an array when target is `selected` | Updates the limit for selected retailer IDs or every retailer; returns `updated_count` |
| `GET /api/admin/retailers/export` | None | Downloads an XLSX retailer export |

Optional retailer profile fields are `shop_name`, `address`, `city`, `county`, `postcode`, `vat_number`, `company_reg_number`, and `utr_number`.

The bulk limit endpoint only updates records whose role is `retailer`. For `target: "selected"`, every ID must identify a retailer. For `target: "all"`, IDs are not used to select records; `retailer_ids` may be omitted or empty, and any supplied IDs must still identify retailers.

## Admin transactions

| Method and path | Parameters | Description |
|---|---|---|
| `GET /api/admin/transactions` | Optional `search`, `status`, `retailer_id`, `operator_id`, `from`, `to`, `page` | Paginated transactions (50 per page), stats, retailer/operator filter options |
| `GET /api/admin/transactions/{transaction}` | Transaction ID | Full transaction with retailer/operator/country |
| `POST /api/admin/transactions/{transaction}/refund` | No body | Refunds only successful transactions; returns `409` otherwise |
| `GET /api/admin/transactions/export` | None | Downloads an XLSX transaction export |

## Admin operators

| Method and path | Parameters | Description |
|---|---|---|
| `GET /api/admin/operators` | Optional `search`, `country_id`, `is_active`, `page` | Paginated operators and countries (50 per page) |
| `POST /api/admin/operators` | Operator payload below | Creates operator; returns `201` |
| `GET /api/admin/operators/{operator}` | Operator ID | Operator details |
| `PUT/PATCH /api/admin/operators/{operator}` | Same required payload as create | Updates operator |
| `DELETE /api/admin/operators/{operator}` | Operator ID | Deletes operator |
| `POST /api/admin/operators/sync` | No body | Syncs Ding providers for active GB; returns `synced` and `failed_countries` |

Operator payload requires `name`, `provider_code`, and valid `country_id`. Optional fields: `valuetopup_operator_id` (integer), `logo_url` (URL), `display_order` (integer >= 0), booleans `is_active`/`is_premium`, and strings `region_codes`, `payment_types`, `validation_regex`, `customer_care_number`.

## Admin settings

| Method and path | Parameters | Description |
|---|---|---|
| `GET /api/admin/settings` | None | Settings grouped by category |
| `PUT /api/admin/settings` | JSON `settings`: non-empty array of `{ "id": integer, "value": any }` | Updates settings in one database transaction |

Example:

```json
{ "settings": [{ "id": 1, "value": "Example" }, { "id": 2, "value": true }] }
```

## Admin allowed-number register

| Method and path | Parameters | Description |
|---|---|---|
| `GET /api/admin/allowed-numbers` | Optional `search`, `folder` (`manual` or file ID), `inactive` (boolean), `page` | Folder list or paginated numbers (50 per page) |
| `POST /api/admin/allowed-numbers` | Manual-number fields | Creates an allowed number; returns `201` |
| `PUT/PATCH /api/admin/allowed-numbers/{allowed_number}` | Manual-number fields plus required boolean `active` | Updates an allowed number |
| `DELETE /api/admin/allowed-numbers/{allowed_number}` | Number ID | Deletes a number |
| `POST /api/admin/allowed-numbers/import-file` | Multipart form-data field `file` | Imports CSV, TXT, or XLSX up to 2048 KB; returns `201` with the file record, imported/skipped counts, and up to 200 skipped-row details |
| `GET /api/admin/allowed-numbers/sample` | None | Downloads sample CSV |
| `GET /api/admin/allowed-numbers/files/{allowedNumberFile}/download` | File ID | Downloads original import |
| `DELETE /api/admin/allowed-numbers/files/{allowedNumberFile}` | File ID | Deletes an imported file and associated records |

Manual number payload fields follow the web form: `mobile`, `serial`, `provider`, `country`; update also requires `active`. CSV/XLSX headers must include `mobile`, `serial`, `provider`, and `country` (case-insensitive). File names must be unique. Use multipart form-data for upload; do not manually set its boundary header.

## NEW — retailer commissions and offers

These endpoints require a bearer token, active retailer account, retailer role, and approved KYC. Send `Accept: application/json`.

### `GET /api/retailer/commissions`

Returns the retailer's current commission level and connection counts, next level threshold, active commission rates, credited commission history, date/type filters, and totals for today, this month, and all time. History is paginated at 25 rows per page.

| Query parameter | Allowed values / behavior |
|---|---|
| `type` | Optional `first` or `ongoing` |
| `from`, `to` | Optional dates; `to` must be on or after `from` |
| `page` | Optional paginator page |

History is always scoped to the authenticated retailer and only includes credited commissions.

### `GET /api/retailer/offers/eligible`

Finds offers usable for the specified recharge amount and operator. Query parameters: required numeric `amount` (minimum `0`), optional integer `operator_id`. Response contains `success: true` and `offers`; each offer includes its benefit for the amount, matching providers, and remaining uses.

### Retailer dashboard and recharge changes

`GET /api/retailer/dashboard` now includes an `offers` array containing visible live offers for the authenticated retailer. When submitting `POST /api/retailer/recharge`, an optional integer `offer_id` reserves that offer for the recharge. The offer is validated again during reservation; a no-longer-eligible offer returns `422`. Cashback is settled only after a successful recharge.

## NEW — admin retailer analytics

`GET /api/admin/analytics` requires an active admin account. All filters are optional:

| Query parameter | Allowed values / behavior |
|---|---|
| `search` | Up to 100 characters; searches retailer name, shop, email, phone, and postcode |
| `network_id` | Existing operator ID |
| `connections` | `5`, `10`, or `20`; minimum connections, counted within the selected network when `network_id` is set |
| `status` | `active` or `inactive` |
| `kyc_status` | `pending`, `approved`, or `rejected` |
| `city` | Exact city name |
| `registered_from`, `registered_to` | Registration date range |
| `activity_from`, `activity_to` | Transaction activity date range |

The response `data` contains the applied filters, network and city options, retailer/transaction summary stats, connection distribution, network breakdown, and highest- and lowest-connection retailer reports. Retailer, network, status, KYC, city, registration-date, and connection-threshold filters narrow both connection reports and summary counts. Activity dates filter transaction metrics in the summary and retailer report rows.

## NEW — admin commissions

All routes in this section require an active admin account and `Accept: application/json`.

### Commission levels and rates

| Method and path | Request / result |
|---|---|
| `GET /api/admin/commission-levels` | Returns commission levels, selected level, that level's rates/steps, and operator options. Optional query `level` selects a level. |
| `POST /api/admin/commission-levels` | JSON: required `name`, `min_connections`; optional `sort_order`, `is_active`. Returns `201`. |
| `PUT /api/admin/commission-levels/{level}` | Same fields as create; updates the level. |
| `DELETE /api/admin/commission-levels/{level}` | Deletes an unused level; returns `409` while retailers are assigned to it. |
| `POST /api/admin/commission-levels/{level}/copy-rates` | JSON `source_level_id`; copies rates and ongoing bands into an empty level. |
| `POST /api/admin/commission-rates` | JSON rate fields below; returns `201`. |
| `PUT /api/admin/commission-rates/{rate}` | Same JSON fields as create. |
| `DELETE /api/admin/commission-rates/{rate}` | Deletes a rate. |

Commission-rate JSON fields: required `commission_level_id`, `min_amount`, and `first_topup_amount`, `first_bonus_amount`; optional `operator_id`, `max_amount`, `is_active`, and `steps`. Each `steps` item has `from_sequence` (minimum 2), optional `to_sequence`, and `amount`. Amount ranges for a level/operator and sequence bands cannot overlap; invalid values return `422`.

### Retailer level assignment and history

| Method and path | Request / result |
|---|---|
| `GET /api/admin/retailer-levels` | Paginated retailers, level options, and assignment data. Optional `search`, `level` (level ID or `none`), and `page`. |
| `PUT /api/admin/retailer-levels/{retailer}` | JSON: optional `level_id` (nullable), `auto_level_enabled`, `actual_connections`, and `reason`. |
| `POST /api/admin/retailer-levels/bulk` | JSON: required non-empty `retailer_ids` array; optional `level_id`, `auto_level_enabled`, and `reason`. |
| `GET /api/admin/retailer-levels/{retailer}/history` | Returns up to 50 level changes, newest first. |

### Commission report

`GET /api/admin/commissions` returns paginated commission entries (50 per page), totals, and retailer/operator/level options. Optional filters: `search`, `status` (`credited` or `skipped`), `type` (`first` or `ongoing`), `retailer_id`, `operator_id`, `level_id`, `from`, `to`, and `page`. Dates must be valid and `to` must not precede `from`.

`GET /api/admin/commissions/export` accepts the same filters and downloads an XLSX file.

## NEW — admin offers and redemptions

These routes require an active admin account.

| Method and path | Request / result |
|---|---|
| `GET /api/admin/offers` | Returns offers, redemption totals, operator options, and retailer options. |
| `POST /api/admin/offers` | Creates an offer from the JSON fields below; returns `201`. |
| `PUT /api/admin/offers/{offer}` | Updates an offer using the same JSON fields. |
| `DELETE /api/admin/offers/{offer}` | Deletes an unused offer; returns `409` if it has redemption history. |
| `GET /api/admin/offers/{offer}/redemptions` | Returns up to 100 most recent redemption rows for the offer. |

Offer JSON fields: required `title`, `type` (`percent` or `fixed`), `value`, `expires_at`, and `audience` (`all` or `selected`); optional `description`, `max_cashback`, `min_amount`, `max_amount`, `starts_at`, `per_retailer_limit`, `total_limit`, `is_active`, `operator_ids`, and `retailer_ids`. Percentage values cannot exceed 100. A selected audience requires at least one retailer ID. The API reuses the same eligibility, reservation, and settlement rules as web recharge.

## NEW — Itemized SIM orders, dynamic products, and retailer order limits

**Latest web-parity changes:** Retailers can order multiple provider/category products in one order. Admins can add products, bulk-update their prices, and remove products. Each order item stores the product category and unit-price snapshot so later catalog changes do not alter order history.

These are the mobile APIs for the itemized SIM ordering functionality added to the web application. They use Sanctum bearer tokens and JSON. Admin endpoints require an active admin account; retailer endpoints require an active retailer account and approved KYC. SIM quantities and limits are counts of SIM cards, not currency values. The product catalog is managed by admins and includes provider, category, and current unit price.

### Retailer SIM orders

| Method and path | Parameters | Result |
|---|---|---|
| `GET /api/retailer/sim-orders` | Optional query `status` (`pending`, `processing`, `dispatched`, `delivered`, `rejected`), `from`, `to`, `page` | Own orders only, paginated 15 per page, with status counts, effective order limit, filters, operators, SIM categories, and current `sim_products` |
| `POST /api/retailer/sim-orders` | JSON `items` array; each row requires `sim_order_product_id` and integer `quantity` (minimum 0). At least one total SIM is required; product IDs must be unique and total quantity cannot exceed the retailer limit. | Creates one pending order with per-product item rows and current unit-price snapshots; notifies admins; returns `201` |

When `users.sim_order_limit` is null, the default limit is **500 SIM cards per order**. The GET response reports the effective limit as `data.sim_order_limit` and `data.stats.sim_order_limit`; the same effective limit is enforced against the sum of all item quantities by POST. Zero-quantity items are ignored when creating the order. Admins can update an individual or bulk retailer limit as described below.

Example create request:

```json
{
  "items": [
    { "sim_order_product_id": 1, "quantity": 10 },
    { "sim_order_product_id": 6, "quantity": 15 }
  ]
}
```

Example create response:

```json
{
  "success": true,
  "message": "SIM card order placed successfully! You will be notified when it is dispatched.",
  "data": {
    "order": {
      "id": 101,
      "quantity": 25,
      "status": "pending",
      "tracking_notes": null,
      "items": [
        {
          "sim_order_product_id": 1,
          "provider_name": "EE",
          "category": "10GBP",
          "quantity": 10,
          "unit_price": "10.00"
        },
        {
          "sim_order_product_id": 6,
          "provider_name": "Vodafone",
          "category": "10GBP",
          "quantity": 15,
          "unit_price": "10.00"
        }
      ],
      "dispatched_at": null,
      "delivered_at": null,
      "created_at": "2026-11-12T06:30:00+00:00"
    }
  }
}
```

### Admin SIM order management

| Method and path | Parameters | Result |
|---|---|---|
| `GET /api/admin/sim-orders` | Optional query `search` (name, shop, or email; max 100 characters), `status`, `from`, `to`, `page` | Paginated orders (15 per page), itemized products and snapshotted prices, global status/quantity stats, last-30-day daily summary, filters, current `sim_products`, and retailer details including each retailer's effective SIM order limit |
| `PUT /api/admin/sim-orders/{simOrder}` | JSON `status` (one of the five statuses above); optional `tracking_notes` (max 1000 characters) | Updates status/notes, sets dispatch/delivery timestamps the first time the corresponding status is entered, notifies the retailer, and returns order with items |
| `PUT /api/admin/sim-orders/retailers/{retailer}/limit` | JSON `sim_order_limit` (integer from 1 through 100000) | Updates the retailer's per-order limit and returns the retailer ID and new limit |
| `GET /api/admin/sim-orders/products` | None | Lists current SIM products (`id`, `provider_name`, `category`, `price`) |
| `POST /api/admin/sim-orders/products` | JSON `provider_name` (string, max 80), `category` (string, max 20; unique per provider), `price` (0–100000, max 2 decimal places) | Adds a product; returns `201` and `data.sim_product` |
| `PUT /api/admin/sim-orders/product-prices` | JSON `products` array of unique `{id, price}` entries; each ID must exist and each price must be 0–100000 with at most 2 decimal places | Bulk updates prices; returns the updated products and `updated_count` |
| `DELETE /api/admin/sim-orders/products/{simOrderProduct}` | Product ID | Deletes the current product; existing order item price/category snapshots remain, and the deleted product reference becomes null |

The existing `GET /api/admin/retailers` and `GET /api/admin/retailers/{retailer}` responses also include `sim_order_limit`, so the admin app can display the configured override on the retailer profile. A `null` value means the default of 500 applies; use either limit endpoint above to set an explicit value.

Status request example:

```json
{ "status": "dispatched", "tracking_notes": "Sent by courier." }
```

Limit request example:

```json
{ "sim_order_limit": 300 }
```

The admin API's `data.stats` contains `total`, `pending`, `processing`, `dispatched`, `delivered`, and `this_month_quantity`. `data.monthly_data` contains daily rows with `date`, `total_quantity`, and `total_orders`. API timestamps are ISO 8601 strings or `null`. Invalid limits/statuses/product IDs/prices or quantities beyond the effective retailer limit return `422`; a retailer calling an admin route receives `403`.

## NEW — SIM provider-file comparisons

These admin-only Sanctum endpoints expose the SIM reconciliation and provider-file comparison functionality added to the web application. Send a bearer token for an active admin account and `Accept: application/json`.

| Method and path | Parameters | Result |
|---|---|---|
| `GET /api/admin/sim-comparisons` | Optional `page` query parameter | Paginated history (15 per page), summary counts, provider IDs/names, admin/operator details, and an `operators` array for selecting providers. Detailed row data is returned by the show endpoint, not included in the list. |
| `POST /api/admin/sim-comparisons` | Multipart form-data: required `operator_ids` array (one or more existing operator IDs), `reconciliation_month` (`YYYY-MM`), and `report_file` (XLSX, XLS, CSV, or TXT; max 10 MB) | Compares the provider report with successful internal transactions for the selected operators and month. Returns `201` and the saved comparison. |
| `GET /api/admin/sim-comparisons/{simComparison}` | Path `simComparison` is the comparison ID | Returns the summary and full `report_data` for matched, missing-from-provider, and additional provider records. |

**Web parity added in the latest changes:** comparisons can include multiple providers. The upload filters provider rows against the selected operators (including aliases such as `3 UK` for `Three`) and matches numbers using their last 10 digits after removing non-digits. Repeated numbers are compared by row count, so unmatched repeat transactions appear in `missing_from_provider` or `additional_in_provider`.

The uploaded file must pass content-based validation for a supported format, use one of the allowed extensions, and include the case-insensitive headers `Provider`, `Mobile`, `Serial`, and `Country`. The API response contains `success`, `message` where applicable, and `data.comparison`; the saved result includes `operator_ids` and an `operators` array. Validation or report-content errors return `422` with Laravel's standard `message` and `errors` object; unexpected processing failures use the framework's standard `500` response.

Bulk limit request example:

```json
{
  "target": "selected",
  "retailer_ids": [42, 43],
  "sim_order_limit": 35
}
```

Bulk limit response format:

```json
{
  "success": true,
  "message": "SIM order limit updated to 35 for 2 retailer(s).",
  "data": {
    "target": "selected",
    "sim_order_limit": 35,
    "updated_count": 2
  }
}
```

Example upload response format:

```json
{
  "success": true,
  "message": "SIM connection reconciliation completed successfully.",
  "data": {
    "comparison": {
      "id": 12,
      "admin_id": 1,
      "operator_id": 3,
      "operator_ids": [3, 4],
      "reconciliation_month": "2026-11",
      "file_name": "provider_report.csv",
      "provider_name": "GiffGaff",
      "total_internal": 2,
      "total_provider": 2,
      "matched_count": 1,
      "missing_count": 1,
      "additional_count": 1,
      "report_data": {
        "matched": [
          {
            "mobile": "07123456789",
            "serial": "SN12345",
            "provider": "GiffGaff",
            "country": "UK",
            "retailer": {
              "id": 42,
              "name": "Example Retailer",
              "shop_name": "Example Shop",
              "email": "retailer@example.com"
            },
            "internal_date": "12 Nov 2026 06:20"
          }
        ],
        "missing_from_provider": [
          {
            "id": 1001,
            "mobile": "07987654321",
            "serial": "TXN-1001",
            "provider": "GiffGaff",
            "country": "United Kingdom",
            "retailer": {
              "id": 42,
              "name": "Example Retailer",
              "shop_name": "Example Shop",
              "email": "retailer@example.com"
            },
            "created_at": "12 Nov 2026 06:15"
          }
        ],
        "additional_in_provider": [
          {
            "provider": "GiffGaff",
            "mobile": "07000111222",
            "serial": "SN67890",
            "country": "UK"
          }
        ]
      },
      "admin": { "id": 1, "name": "Admin User" },
      "operator": { "id": 3, "name": "GiffGaff" },
      "operators": [
        { "id": 3, "name": "GiffGaff" },
        { "id": 4, "name": "Three" }
      ],
      "created_at": "2026-11-12T06:30:00+00:00",
      "updated_at": "2026-11-12T06:30:00+00:00"
    }
  }
}
```

The show response uses the same `data.comparison` shape. The list response uses `data.comparisons` for pagination and `data.operators` for operator choices; each list row omits `report_data`.

## Provider webhooks

`POST /api/webhooks/ding` and `POST /api/webhooks/stripe` are server-to-server callback routes, not mobile-client endpoints. Configure them with the relevant provider and do not expose provider credentials or use the app user's bearer token. Ding callbacks are queued for processing. Stripe callback processing can return `{"status":"success"}` or `{"status":"ignored"}`.

## Postman collection

Import [DingConnect API.postman_collection.json](./DingConnect%20API.postman_collection.json). Set the collection's `base_url` to your application origin and paste a token into `token`. The collection automatically saves a token from successful registration or OTP verification responses. It includes auth, public lookup, retailer, admin, SIM order and SIM comparison (including multi-provider upload), webhook, upload, and export requests.

Every request has a saved **Success response format** example under its Postman **Responses** tab. These examples show the JSON keys/wrappers, HTTP success status, and content type. Download endpoints use illustrative file-content placeholders (or a small CSV sample); capture the live response to save the real PDF/XLSX/export file. The examples use dummy values and do not represent live account data.
