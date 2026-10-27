# DingConnect Mobile API

This reference describes the application API under `/api`. It is intended for mobile clients and uses Laravel Sanctum personal access tokens.

> **NEW API coverage — added since baseline `e16d8f6efa17fca87d88a334455c2814b717ae89`:** retailer commission summaries/history and offer eligibility; active offers in the retailer dashboard and offer selection on recharge; admin retailer analytics, commission-level/rate configuration, retailer level assignment/history, commission reports, and offer/redemption management. These routes are also grouped under **NEW** folders in the Postman collection.
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
| `GET /api/retailer/transactions/{transaction}/receipt` | Transaction ID | Receipt response/download according to transaction state |

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
| `GET /api/admin/retailers/export` | None | Downloads retailer export |

Optional retailer profile fields are `shop_name`, `address`, `city`, `county`, `postcode`, `vat_number`, `company_reg_number`, and `utr_number`.

## Admin transactions

| Method and path | Parameters | Description |
|---|---|---|
| `GET /api/admin/transactions` | Optional `search`, `status`, `retailer_id`, `operator_id`, `from`, `to`, `page` | Paginated transactions (50 per page), stats, retailer/operator filter options |
| `GET /api/admin/transactions/{transaction}` | Transaction ID | Full transaction with retailer/operator/country |
| `POST /api/admin/transactions/{transaction}/refund` | No body | Refunds only successful transactions; returns `409` otherwise |
| `GET /api/admin/transactions/export` | None | Downloads transaction export |

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
| `POST /api/admin/allowed-numbers/import-file` | Multipart form-data field `file` | Imports CSV/TXT up to 2048 KB |
| `GET /api/admin/allowed-numbers/sample` | None | Downloads sample CSV |
| `GET /api/admin/allowed-numbers/files/{allowedNumberFile}/download` | File ID | Downloads original import |
| `DELETE /api/admin/allowed-numbers/files/{allowedNumberFile}` | File ID | Deletes an imported file and associated records |

Manual number payload fields follow the web form: `mobile`, `serial`, `provider`, `country`; update also requires `active`. CSV headers must include `mobile`, `serial`, `provider`, and `country` (case-insensitive). File names must be unique. Use multipart form-data for upload; do not manually set its boundary header.

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
| `PUT /api/admin/retailer-levels/{retailer}` | JSON: optional `level_id` (nullable), `auto_level_enabled`, `actual_connections`, `topups_count`, and `reason`. |
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

## Provider webhooks

`POST /api/webhooks/ding` and `POST /api/webhooks/stripe` are server-to-server callback routes, not mobile-client endpoints. Configure them with the relevant provider and do not expose provider credentials or use the app user's bearer token. Ding callbacks are queued for processing. Stripe callback processing can return `{"status":"success"}` or `{"status":"ignored"}`.

## Postman collection

Import [DingConnect API.postman_collection.json](./DingConnect%20API.postman_collection.json). Set the collection's `base_url` to your application origin and paste a token into `token`. The collection automatically saves a token from successful registration or OTP verification responses. It includes auth, public lookup, retailer, admin, webhook, upload, and export requests.
