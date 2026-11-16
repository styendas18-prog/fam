# Subscription plans and usage limits

MAXXfam includes a plan catalogue, a merchant request/approval flow, and enforced monthly limits for payment orders, API requests and payment links.

A merchant with **no plan assigned** has no plan-level limits. A limit value of **`0` means unlimited**. Usage is counted per calendar month in UTC from real rows — never from fabricated counters.

---

## Admin: managing plans

Open **Admin → Plans** (permission `plans.view` to read, `plans.manage` to change).

A plan has:

| Field | Meaning |
|---|---|
| Name / slug | Display name and a unique URL-safe slug. |
| Description | Short merchant-facing copy. |
| Price / billing interval | `monthly`, `yearly`, or `one_time`. |
| Transaction limit | Payment orders per calendar month; `0` = unlimited. |
| API limit | API v1 requests per calendar month; `0` = unlimited. |
| Link limit | Payment links created per calendar month; `0` = unlimited. |
| Features | One feature per line, shown on the merchant plan card. |
| Active / featured / sort order | Catalogue visibility and ordering. |

Actions:

- **Create** — new plan, immediately available when active.
- **Edit** — update pricing, limits, features and status.
- **Enable / Disable** — hides the plan from the merchant catalogue. A disabled plan that is already assigned continues to be visible to that merchant but no new catalogue requests are possible.
- **Delete** — blocked while any merchant is assigned. Pending requests for that plan are removed with it.
- **Approve / Reject request** — approves assign the plan and activate its expiry; rejects keep the merchant's existing plan.

`support` administrators can view plans but cannot change them.

---

## Merchant: requesting a plan

Open **Account → Plans** in the merchant panel.

The page shows:

- current plan, price, start date and expiry (or `Lifetime`)
- this month's usage and limit progress for orders, API requests and links
- every active plan with limits and features
- the pending request (if any), with a **Cancel request** action
- request history with admin notes

A merchant can have only one pending request at a time and cannot request the plan they already hold. Requesting is not self-service activation: an administrator must approve it.

---

## Approval and expiry

When a request is approved:

1. `merchants.plan_id` is set to the plan.
2. `plan_started_at` is set to now.
3. `plan_expires_at` is set based on the interval: `+1 month`, `+1 year`, or `NULL` (lifetime) for `one_time`.
4. `plan_status` becomes `active`.
5. The merchant receives a `plan_approved` notification.
6. The request is marked `approved` with the reviewing admin and note.

Rejection marks the request `rejected` and sends a `plan_rejected` notification. The existing plan is not changed.

An assigned plan whose expiry has passed, or whose `plan_status` is not `active`, is treated as unusable: new payment orders, API requests and links are blocked until a new plan is approved. Usage data is still visible.

---

## Enforcement points

| Action | Where enforced | Result at limit |
|---|---|---|
| Create payment order | `App\Services\PaymentService::create()` | `PlanLimitException`; the dashboard shows a flash error |
| API v1 request | `public/api/v1/index.php` after authentication | HTTP `429` with `code: plan_limit_exceeded` |
| Create payment link | `App\Models\PaymentLinkModel::create()` | `PlanLimitException`; the page shows a flash error |
| Create payment order from API / link checkout | `PaymentService::create()` | HTTP `429` `plan_limit_exceeded` |

Limits are checked against real rows created since the first day of the current UTC month:

- payment orders → `payment_orders`
- API requests → `api_logs`
- payment links → `payment_links`

The current request is counted only after it is recorded, so the N‑th request is allowed and the next one is blocked.

---

## Database

Migration `database/migrations/004_plans.sql` creates:

- `plans`
- `plan_requests`
- `merchants.plan_id`, `plan_started_at`, `plan_expires_at`, `plan_status`

No plans are seeded. Create the first plan from **Admin → Plans**.

---

## Operational notes

- Changing a plan's limits affects all merchants currently assigned to it immediately.
- Deleting a plan is blocked while assigned; move merchants (approve a new request) first.
- Plan prices are catalogue values only. MAXXfam does not charge them automatically and documents no subscription billing API; collect payment separately if required.
- The pending request queue is shown on the same admin page. Reviews are audit logged (`plan_request_approved` / `plan_request_rejected`).
