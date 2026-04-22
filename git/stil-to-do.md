# Trustees — Still To Do

Punch-list of what's still open from [project-audit-v4.md](project-audit-v4.md), re-verified against the code on 2026-04-22. Section numbers match the v4 audit so you can cross-reference.

---

## Already done since the v4 audit (skip these)

- **All of Section 1 (Critical).** `register.php` bind_param is `"sssss"`, `Admin/verify_users.php` approve branch has its `prepare()`, `Listings/create.php` is on prepared statements + has the auth include + mime-validates uploads, `Listings/view.php` include uses `Includes` (capital I), `Admin/listings.php` no longer double-includes.
- **All of Section 5 (empty files).** `Profile/wallet.php`, `Profile/profile.php`, `Verification/verify.php`, `Orders/my_orders.php`, `Orders/my_sales.php`, `Admin/disputes.php`, `Listings/search.php`, `CSS/style.css` all have content. `Orders/checkout.php` exists too — buy flow is scaffolded.
- **Section 4a.** [Includes/header.php](../Includes/header.php) is now a single clean if/elseif/else — no more duplicate user/icons block.
- **Section 6 schema bulk.** Foreign keys in place across listings/orders/disputes/wallet/verifications, `wallet.user_id` is UNIQUE, `verifications` table now has selfie_photo / verification_video / full_name / id_number / address / rejection_reason / reviewed_at / reviewed_by.
- **Section 3 casing.** Most paths fixed; one straggler + one regression below.

---

## HIGH — do before deployment

- **2c. CSRF tokens missing on every POST form.** Admin approve/reject (`Admin/verify_users.php`, `Admin/verify_listings.php`), `Listings/create.php`, `Verification/verify.php`. Highest risk on the admin actions.
- **3. Casing regression in [login.php:37](../login.php#L37).** Reads `href="CSS/styles.css"` but the file is `CSS/style.css`. 404s on Linux. One-character fix.
- **2d. DB credentials hardcoded in [Includes/db.php](../Includes/db.php).** Move to `.env` (and `.gitignore` it) before deploying anywhere public.
- **6f. `category` mismatch.** [Listings/browse.php](../Listings/browse.php) filters 7 categories; [Listings/create.php](../Listings/create.php) only offers 3. Buyers can filter for categories nobody can list. Make it an ENUM (or a `categories` table) keyed off the same source.
---

## MEDIUM — should do, not blocking

- **2b. `session_start()` is still scattered.** `Includes/session.php` wrapper exists but isn't the single source of truth — `login.php`, `logout.php`, `Includes/auth.php`, `Includes/header.php` all still call `session_start()` directly. Make `db.php` include `session.php` and remove the rest.
- **2a. [register.php](../register.php) doesn't start a session on success.** User has to log in again right after signing up. Either set `$_SESSION['user_id'] = $conn->insert_id` and redirect to dashboard, or note as a known UX wart.
- **2e. [login.php](../login.php) has no rate limiting / attempt tracking.** Brute-force open. Future Work item.
- **6c. `wallet_transactions` missing `order_id` and `balance_after`.** Adds traceability for the buy flow.

---

## LOW — nice to have

- **6d. `orders` missing `unit_price_at_purchase`.** `total_price` is enough for this project; only add if time.
- **7b.** No flash-message pattern for login failures.
- **7c. [index.php](../index.php)** missing `<html>`/`</html>` wrapper tags — header/footer don't supply the outer structure either. Quick HTML validation pass.

---

## Submission prep (Section 8 Phase 5 + Section 9)

- [ ] Walk the happy path end-to-end in two browser profiles (buyer + admin): register → verify → list → admin approves → buyer buys → wallet moves → order visible to both sides.
- [ ] Load every page on a fresh XAMPP and confirm zero PHP warnings.
- [ ] Confirm [database/trustees_db.sql](../database/trustees_db.sql) matches the live DB.
- [ ] Deploy to a Linux host (InfinityFree or similar) — the casing fix in `login.php` won't surface locally.
- [ ] Write the report's "Future Work" section: payment gateway, real KYC provider, POPIA compliance, reviews, messaging, image gallery, rate limiting, CSRF, password reset.

---

## Section 10 (verification flow) — re-check separately

The audit's Section 10 / v3 Section 11 covers the seller-verification UX, POPIA, storage outside web root, etc. The schema and form file now exist, but the deeper checklist (consent record, audit log, delete-my-data, files locked behind `.htaccess` deny + PHP proxy, `upload_max_filesize` bumps) hasn't been verified — review [project-audit-v4.md](project-audit-v4.md) Section 10 and walk it as its own pass.
