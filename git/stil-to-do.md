# Trustees — Still To Do

Re-audited against the code on 2026-04-24, after the CSRF + rate-limiting commits (`116f30d`, `46ceeec`) landed. Cross-references [project-audit-v4.md](project-audit-v4.md) where the item originated.

The theme of this pass: several recent changes were added to the schema but not carried through to every form/handler, and the CSRF helper was added to some forms but not all. That's where most of the open items live.

---

## Done since the 2026-04-22 re-verify (skip these)

- **v4 Section 1 (Critical)** — all five still fixed. `register.php` bind_param is `"sssss"`, the approve branch in `Admin/verify_users.php` has its `prepare()`, `Listings/create.php` is on prepared statements + auth + MIME validation, `Listings/view.php` uses `Includes/` (capital I), `Admin/listings.php` no longer double-includes.
- **v4 2a duplicate email.** [register.php:17](../register.php#L17) now catches `mysqli_errno($conn) === 1062` and shows a friendly message instead of leaking schema.
- **v4 2e rate-limiting on login.** [login.php:9-24](../login.php#L9-L24) counts failed attempts per-email over the last minute, blocks at 5, and logs every attempt (success or fail) to `login_attempts`. Table + composite indexes are in [database/trustees_db.sql:146-153](../database/trustees_db.sql#L146-L153).
- **v4 Section 3 casing sweep.** [login.php](../login.php), [register.php](../register.php), [Includes/header.php](../Includes/header.php), [Includes/footer.php](../Includes/footer.php), [Listings/view.php](../Listings/view.php) all now reference the correct-case paths (`/ITECA-Website/CSS/style.css`, `/ITECA-Website/JavaScript/main.js`, lowercase `login.php`/`logout.php`). No Linux-casing bugs found on a fresh grep.
- **v4 Section 4a header duplication.** [Includes/header.php](../Includes/header.php) is one clean if/elseif/else auth branch.
- **v4 Section 5 empty files.** All populated: `Profile/wallet.php` (124 lines), `Profile/profile.php` (176), `Verification/verify.php` (229), `Orders/checkout.php` (176), `Orders/my_orders.php` (127), `Orders/my_sales.php` (120), `Admin/disputes.php` (141), `Listings/search.php` (144), `CSS/style.css` (7.4 KB).
- **v4 6a/6b/6e schema bulk.** FKs in place across listings/orders/disputes/wallet/verifications in [database/trustees_db.sql:293-321](../database/trustees_db.sql#L293-L321). `wallet.user_id` is UNIQUE. `verifications` table has selfie_photo / verification_video / full_name / id_number / address / rejection_reason / reviewed_at / reviewed_by.
- **v4 6f categories.** `Listings/create.php` and `Listings/browse.php` now offer the same set.
- **v4 2d DB credentials.** [Includes/db.php](../Includes/db.php) reads from `.env`. (Still confirm `.env` is in `.gitignore` — see M6.)
- **v4 2c CSRF.** Partially done — helper exists, 4 of 13 handlers wired up. See H1 below for the gaps.

---

## HIGH — fix before submission

### H1. CSRF covers only 4 of 13 POST handlers

The helper exists at [Includes/auth.php:3-5](../Includes/auth.php#L3-L5) and generates one 64-char token per session in `$_SESSION['csrf_token']`.

**Done on:** [Listings/create.php](../Listings/create.php), [Admin/verify_users.php](../Admin/verify_users.php), [Admin/verify_listings.php](../Admin/verify_listings.php), [Verification/verify.php](../Verification/verify.php).

**Missing on (each needs the hidden input in the form + a `hash_equals` check at the top of the POST handler):**

- [login.php:66](../login.php#L66) + [register.php:31](../register.php#L31) — session-fixation vectors; also lets attackers mass-submit signups.
- [Orders/checkout.php:153](../Orders/checkout.php#L153) — the biggest one. Without CSRF, a logged-in buyer can be tricked into draining their wallet on any listing.
- [Orders/my_orders.php:112](../Orders/my_orders.php#L112) — buyer confirming delivery (triggers fund release).
- [Orders/my_sales.php:100,105](../Orders/my_sales.php#L100) — seller status transitions.
- [Admin/disputes.php:116,122,128](../Admin/disputes.php#L116) — admin resolve/close/reopen.
- [Admin/orders.php:74](../Admin/orders.php#L74) — admin order status update.
- [Profile/profile.php:143,161](../Profile/profile.php#L143) — profile edit + password change.
- [Profile/wallet.php:88](../Profile/wallet.php#L88) — wallet deposit.

Side note: the existing handlers use `die("CSRF token validation failed.")`. Swap for a `set_flash('error', ...)` + redirect so the user isn't stuck on a bare-text page. Covered below as L4.

### H2. Orphan schema columns — added but never written

Schema has these columns; no code ever INSERTs into them. A marker grepping the SQL against the code will notice. Either populate or drop.

- **`orders.unit_price_at_purchase`** — [database/trustees_db.sql:78](../database/trustees_db.sql#L78). Never populated. [Orders/checkout.php:93-96](../Orders/checkout.php#L93-L96) INSERTs `(buyer_id, listing_id, delivery_method, delivery_address, status, quantity, total_price)` — add `unit_price_at_purchase` (while quantity is locked to 1, it equals `total_price`). Read sites in [Orders/my_orders.php](../Orders/my_orders.php), [Orders/my_sales.php](../Orders/my_sales.php), [Admin/orders.php](../Admin/orders.php) can stay as-is for now.
- **`wallet_transactions.order_id` and `wallet_transactions.balance_after`** — [database/trustees_db.sql:165-168](../database/trustees_db.sql#L165-L168). Three INSERT sites, all missing both columns:
  - [Orders/checkout.php:107](../Orders/checkout.php#L107) — the "hold" on purchase. Pass `order_id = $conn->insert_id` (captured right after the `orders` INSERT) and `balance_after` = the pre-hold balance minus price (the `FOR UPDATE` row read at [checkout.php:73](../Orders/checkout.php#L73) has the starting balance).
  - [Orders/my_orders.php:42-44](../Orders/my_orders.php#L42-L44) — the "release" when buyer confirms delivery. `order_id` from the confirm form, `balance_after` from the seller's wallet read.
  - [Profile/wallet.php:41-43](../Profile/wallet.php#L41-L43) — the "deposit". `order_id = NULL`, `balance_after` = new balance after deposit.
- **`listings.rejection_reason`** — [database/trustees_db.sql:58](../database/trustees_db.sql#L58). Admin rejects at [Admin/verify_listings.php:15-17](../Admin/verify_listings.php#L15-L17) with status only; reason never captured. Add a `rejection_reason` `<textarea>` to the reject form, bind it in the UPDATE, and surface it to the seller (e.g. on a "My Listings" page, or at least on `Listings/view.php` when the viewer is the owner). The same pattern is already in place for `verifications.rejection_reason` — copy that flow.

### H3. Dead link: "Contact Seller" → `sellerinfo.php`

[Listings/view.php:32](../Listings/view.php#L32) renders a Contact Seller button pointing at `../sellerinfo.php`. **File does not exist** (Glob confirmed). Two options:

- **Fast fix:** delete the button for submission.
- **Real fix:** build a minimal `sellerinfo.php` that SELECTs `name, phonenr, created_at` from `users` by id, auth-gated to logged-in users only.

The listings `JOIN` at [Listings/view.php:9-13](../Listings/view.php#L9-L13) already fetches seller name — the simplest build swaps the button for a small "Seller: *name*" block with a `mailto:` or `tel:` link rather than a dedicated page.

### H4. No user-facing dispute form

`disputes` table has `reason` + `evidence` columns, and `Admin/disputes.php` can *manage* disputes, but **there is no page for a buyer to file a dispute**. The table gets populated only by [database/seed.sql:45-47](../database/seed.sql#L45-L47). The proposal's "buyer protection" story depends on this working end-to-end.

Two options:

- **Build it:** `Orders/dispute.php` — buyer opens it from their "My Orders" row (for delivered/inspecting orders only), submits reason + optional evidence image (use the same MIME/extension + GD validation as `Listings/create.php`). Store to `Uploads/disputes/` with its own `.htaccess` matching `Uploads/verification/`.
- **Scope out:** remove the dispute rows from seed, remove `Admin/disputes.php` from the admin nav, mention "dispute filing UI" as Future Work in the report.

---

## MEDIUM — should do, not blocking

### M1. Rate limiting only on login

[login.php:9-24](../login.php#L9-L24) is the only caller of `login_attempts`. Not rate-limited: [register.php](../register.php) (signup spam, email enumeration via duplicate-key error), [Verification/verify.php](../Verification/verify.php) (50 MB video uploads — trivial DoS), [Profile/wallet.php](../Profile/wallet.php) (deposit). Per-IP limit on registration is the highest-value add.

### M2. `register.php` still doesn't start a session on success

[register.php:14-16](../register.php#L14-L16) redirects to `login.php` after a successful INSERT. User has to log in manually right after signing up. Fix:

```php
$_SESSION['user_id']   = $conn->insert_id;
$_SESSION['user_name'] = $name;
$_SESSION['role']      = 'buyer';
header("Location: index.php");
```

### M3. `seed.sql` is out of sync with `trustees_db.sql`

Demo data only, but the README tells a marker to import both in order. The current `seed.sql` won't import cleanly once `trustees_db.sql` is applied:

- [database/seed.sql:63](../database/seed.sql#L63) uses the old `item_condition` enum `('new','used','refurbished')` and value `'used'` — the schema now requires one of `new/like_new/good/fair/poor/refurbished`, so every seeded listing is invalid.
- [database/seed.sql](../database/seed.sql) listings table is missing `rejection_reason`; status enum is missing `'rejected'`.
- [database/seed.sql](../database/seed.sql) orders table is missing `unit_price_at_purchase`.
- [database/seed.sql:150-157](../database/seed.sql#L150-L157) verifications table is missing 8 columns (selfie_photo, verification_video, full_name, id_number, address, rejection_reason, reviewed_at, reviewed_by).
- [database/seed.sql:202-209](../database/seed.sql#L202-L209) wallet_transactions table is missing `order_id` and `balance_after`.
- `seed.sql` creates none of the FKs, the `wallet.user_id` UNIQUE, or the `login_attempts` table.
- `seed.sql` inserts `listings.user_id` = 2/4/6 and `verifications.user_id` = 2-6, but its own `INSERT INTO users` only creates ids 6-11. Once FKs are enforced (which `trustees_db.sql` does), the seed import will fail on the first listing.

Fix paths: regenerate `seed.sql` from a fresh `trustees_db.sql` + some hand-crafted demo rows, or drop `seed.sql` from the README import step and seed through the UI before demos.

### M4. `session_start()` still scattered

v4 2b. [Includes/session.php](../Includes/session.php) exists and exposes `set_flash`/`get_flash`, but `session_start()` is still called directly in [login.php](../login.php), [logout.php](../logout.php), [Includes/auth.php](../Includes/auth.php), [Includes/header.php](../Includes/header.php). Works in practice (header.php guards with `session_status()`), but it's the inconsistency the next reviewer will spot first.

### M5. `Admin/verify_users.php` orphaned from admin nav

[Admin/dashboard.php](../Admin/dashboard.php) links to `users.php`, `listings.php`, `verify_listings.php`, `disputes.php` — no tile for `verify_users.php`. Admin has to type the URL. One-line fix.

### M6. Confirm `.env` is gitignored

[Includes/db.php](../Includes/db.php) reads from `.env` (v4 2d is done). Double-check `.env` is in `.gitignore` before any public push or deploy.

---

## LOW — nice to have

- **L1.** [Listings/view.php:2](../Listings/view.php#L2) has no auth include. Guests can view listings (correct) but the checkout button URL leaks the listing id; minor.
- **L2.** Flash-message pattern is inconsistent: [login.php](../login.php) uses `set_flash`/`get_flash`, but [register.php:30](../register.php#L30) uses a page-scoped `$register_error`. Unify on the flash helpers.
- **L3.** v4 7c — [index.php](../index.php) HTML-tag balance. Header/footer look clean on a quick read; re-verify with a validator.
- **L4.** `die("CSRF token validation failed.")` in three admin handlers and `Verification/verify.php` — swap for a flash-redirect so the user isn't stuck on a bare-text page.
- **L5.** No debug noise found (no `var_dump`, `print_r`, raw `$_POST` dumps).

---

## Section 10 — seller verification flow (still its own pass)

Schema and form both exist. [Verification/verify.php:22-77](../Verification/verify.php#L22-L77) has MIME + size + GD-re-encode validation and writes to `Uploads/verification/` which has an `.htaccess` denying direct access. What hasn't been verified and is still open from v4 Section 10:

- Consent record / consent checkbox stored on submit.
- Audit log for who-reviewed-what.
- Delete-my-data flow for POPIA.
- `upload_max_filesize` / `post_max_size` / `max_execution_time` bumps for the video upload path.
- Retention policy (when do verification docs get purged).
- Privacy-policy link near the submit button.

Walk these as their own pass against v4 Section 10.

---

## Submission prep checklist

- [ ] Happy-path walk-through in two browser profiles (buyer + admin): register → verify → list → admin approves → buyer buys → wallet moves → order visible to both sides → admin sees order.
- [ ] Zero PHP warnings on a fresh XAMPP.
- [ ] Deploy to a Linux host (InfinityFree or similar) — case-sensitivity regressions surface only there.
- [ ] `database/trustees_db.sql` matches the live DB after every schema change (and `seed.sql` too, if M3 is tackled).
- [ ] Report "Future Work" section: payment gateway, real KYC provider, POPIA compliance framework, reviews, messaging, image gallery, password reset, CSRF-everywhere, per-IP rate limiting, dispute-filing UX.