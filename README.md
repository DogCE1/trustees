# Trustees — The Middle Man You Can Trust

**Live site:** [trustees.rf.gd](https://trustees.rf.gd/Listings/browse.php)

Trustees is a consumer-to-consumer (C2C) marketplace for South Africa's informal economy. Buying second-hand from strangers online is risky, so Trustees adds a physical step. Sellers drop items off at a Trustees store, where staff inspect and verify them. Only then is the buyer told the item is ready. The buyer's money is held in escrow until they have the item.

## How it works

1. A seller lists a product on the platform
2. The seller drops the item off at the nearest Trustees store (the app finds the closest one by distance)
3. A Trustees staff member inspects and verifies the item
4. The buyer pays, and the money is held in escrow
5. The buyer collects the item or chooses delivery, and the money is released to the seller
6. If something goes wrong, the buyer can open a dispute and an admin decides whether to refund or release the money

## Features

**For buyers and sellers**
- Register, log in and verify your identity with an emailed one-time code (OTP)
- Create, edit and browse listings with image uploads, plus live search
- Checkout with a simulated wallet, with every transaction recorded
- Track orders (*My Orders* and *My Sales*), upload proof, and open disputes
- Message other users and get in-app notifications
- Find the nearest drop-off store by distance

**For admins**
- Dashboard with role-based access (buyer, seller, admin)
- Approve listings and identity verifications
- Manage users and orders, and resolve disputes with a refund or a release of funds

## Security

- Passwords are hashed with `password_hash` (bcrypt)
- Every query that takes user input uses a prepared statement, which blocks SQL injection
- Forms that change data carry CSRF tokens
- One-time codes are stored hashed. Each code expires after 5 minutes, allows at most 5 attempts, and can only be requested once a minute
- Login attempts and other sensitive actions are rate-limited
- Wallet changes run inside database transactions with row locking (`SELECT … FOR UPDATE`). A failed order can't leave the money half-moved
- Upload folders are protected with `.htaccess` rules

## Tech stack

| Area | Technology |
|---|---|
| Backend | PHP (mysqli) |
| Database | MySQL |
| Frontend | HTML, CSS, Bootstrap, JavaScript |
| Hosting | InfinityFree (live), XAMPP (local) |

## Running it locally

1. Install [XAMPP](https://www.apachefriends.org/) and start Apache and MySQL.
2. Clone the repo into `htdocs`:
   ```bash
   git clone https://github.com/DogCE1/trustees.git
   ```
3. In phpMyAdmin, create a database and import `database/trustees_db.sql`. Then import `database/seed.sql` for demo data.
4. Create a `.env` file in the project root:
   ```ini
   DB_HOST=localhost
   DB_USER=root
   DB_PASS=
   DB_NAME=trustees_db
   ```
5. Open `http://localhost/trustees/`.

On XAMPP, OTP emails aren't actually sent. The code is also written to `logs/otp.log`, so you can still test verification.

## Project structure

```
Admin/          Admin dashboard: users, listings, orders, disputes, verifications
Includes/       Shared code: database, sessions, auth, escrow, OTP, rate limiting
Listings/       Create, edit, browse and search listings
Orders/         Checkout, my orders and sales, disputes, nearest store lookup
Messages/       User-to-user messaging
Notifications/  In-app notification inbox
Profile/        Profile and wallet
Verification/   Identity verification and OTP
database/       Schema and seed data
```
