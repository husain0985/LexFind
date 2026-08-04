# LexFind – Legal Advisor Finder (Traditional Multi-Page Edition)

This is a full rebuild of LexFind as a **traditional server-rendered PHP
website** — every page is a real file at a real URL
(`about.php`, `search.php`, `client/bookings.php`, etc.), rendered fully
by the server on each request. There is no single-page-app JavaScript
router. Your browser's native **Back / Forward buttons work exactly
like they do on any ordinary website**, because every navigation is a
real page load that the browser tracks in its own history — nothing
custom needed.

## What changed from the single-page version

- **Every "page" is its own PHP file**, not a hidden `<div>` toggled by JavaScript.
- **Forms POST directly to PHP**, which validates, saves to MySQL, and
  redirects (the Post/Redirect/Get pattern) — so refreshing a page never
  re-submits a form.
- **Almost no custom JavaScript.** A few interactive bits that used to
  require JS now use plain HTML/CSS instead:
  - Role tabs (Client/Advisor/Admin) on Login/Signup → radio buttons
    styled as tabs, toggled with CSS `:checked` / `:has()`
  - Weekly availability toggle → checkboxes styled as day tiles
  - Star rating input → radio buttons styled as stars (the classic
    "5 radios in reverse order" CSS trick)
  - FAQ accordion → native `<details>`/`<summary>`, no JS required
  - The hamburger menu and the profile-photo lightbox → a hidden
    checkbox + `<label>` pair, toggled purely with CSS `:checked ~`
  - A few `onsubmit="return confirm(...)"` attributes on destructive
    buttons (Cancel booking, Suspend user) — a browser-native
    confirmation dialog, not a custom script
  - The one real exception: `advisor.php` has a small custom-built
    date-picker calendar (month navigation, greys out days the advisor
    isn't available on) and a show/hide toggle for the WhatsApp number
    field — both in a single `<script>` block on that page.
- **Search bar**: it's a real `<form method="get">`, so pressing Enter
  submits it like any ordinary search box. You can filter by keyword,
  specialty, and city/location, and sort by rating, fee, reviews, or
  location.
- **Booking** happens directly on the advisor's own page (scroll down
  to "Book an Appointment") instead of a JavaScript popup modal.

## Requirements

- XAMPP (Apache + MySQL + PHP 8.x)
- [Composer](https://getcomposer.org) — needed once, to install
  PHPMailer (used for account verification and notification emails)
- A Gmail account with an
  [App Password](https://myaccount.google.com/apppasswords) generated,
  if you want outgoing email to actually work
- (Optional) [ngrok](https://ngrok.com) — only needed if you want
  emailed links (verification, password reset, booking notifications)
  to be clickable from a device other than the one running XAMPP. See
  **Email & ngrok** below for why.

## Setup

1. Copy the `lexfind` folder into your XAMPP `htdocs` directory.
2. Start Apache and MySQL in the XAMPP Control Panel.
3. Open a terminal in the project folder and run `composer install` —
   this pulls in PHPMailer under `vendor/`.
4. Open `http://localhost/phpmyadmin`, **Import** →
   `database/schema.sql` → **Go**. This creates the `lexfind_db`
   database with all tables and demo data (6 approved advisors, 1
   client, 1 admin).
5. Check `config/db.php` — defaults are `root` user, blank password,
   host `localhost`. If your MySQL setup differs (e.g. you get an
   "Access denied" error), the most common fix is changing
   `DB_HOST` to `127.0.0.1`, since some MySQL installs treat
   `root@localhost` and `root@127.0.0.1` as different accounts.
6. Check `config/mail.php` — fill in your own Gmail address and App
   Password so account verification and booking notification emails
   can actually send. If you skip this, the site still works, but
   registration/notification emails will fail to send.
7. Visit `http://localhost/lexfind/setup.php` **once** — this fixes the
   demo accounts' password hashes so they work immediately. You can
   delete `setup.php` afterwards.
8. Open `http://localhost/lexfind/index.php`.

## Email & ngrok

Every emailed link (account verification, password reset, "your
booking was confirmed", etc.) is built from whatever host the request
came in on (`appBaseUrl()` in `includes/auth.php`, which reads
`$_SERVER['HTTP_HOST']`). If you only ever visit the site as
`localhost`, those links only work on your own machine — anyone else
opening the email elsewhere can't reach `localhost` on their own
device.

If you want those links to actually be clickable by someone else (a
client, an advisor, a friend testing signup), run `ngrok http 80` and
visit the site through the `https://xxxx.ngrok-free.app` address ngrok
gives you instead of `localhost`. Since `appBaseUrl()` reads the host
dynamically, the emailed links will automatically use that public
address — no code changes needed. Once this is deployed to real
hosting later, the same mechanism picks up the real domain
automatically and ngrok is no longer needed.

## Demo accounts (password: `Password123` for all)

| Role    | Email                  |
|---------|------------------------|
| Admin   | admin@lexfind.com.bd   |
| Client  | john.doe@email.com     |
| Advisor | rahim@law.com          |
| Advisor | sara.ahmed@law.com     |
| Advisor | nisha@law.com          |
| Advisor | karim@law.com          |
| Advisor | ali@law.com            |
| Advisor | maya@law.com           |

New advisor signups start **pending** and need approval from
Admin → Advisor Approval before appearing in search.

## Project structure

```
lexfind/
├── index.php                     Home
├── about.php / faq.php / contact.php
├── login.php / signup.php / logout.php / admin-login.php
├── forgot-password.php / reset-password.php    Password reset flow
├── verify-email.php / resend-verification.php  Email verification flow
├── search.php                     Search Advisors (GET form: keyword, specialty,
│                                   city/location, sort)
├── advisor.php?id=N               Advisor profile + inline booking form
├── payment.php                    Demo/simulated SSLCommerz-style checkout
│                                   (no real gateway connected — see Notes)
├── setup.php                      One-time demo password fix (delete after running)
├── config/
│   ├── db.php                     Database credentials
│   └── mail.php                   Gmail SMTP credentials (PHPMailer)
├── database/schema.sql            Full schema + seed data
├── assets/css/style.css           All styling (CSS-only widgets, Font Awesome tinting)
├── uploads/
│   ├── avatars/                   Profile photos
│   └── bar_ids/                   Advisors' uploaded Bar Council ID cards
├── includes/
│   ├── auth.php                   Session/auth helpers, requireLogin(), flash
│   │                               messages, email-sending functions, appBaseUrl()
│   ├── header.php / footer.php / dash-footer.php
│   ├── dash-nav.php               Shared dashboard sidebar
│   └── advisor-card.php           Reusable advisor card partial
├── actions/                       Form-handler scripts (validate → save → redirect)
│   ├── book.php
│   ├── cancel-booking.php         (disabled — clients can no longer self-cancel;
│   │                                see Notes)
│   ├── respond-booking.php        (advisor accept/reject)
│   ├── process-payment.php        (demo payment simulation)
│   ├── delete-review.php
│   ├── delete-message.php / toggle-message-read.php   (admin contact-message inbox)
│   ├── admin-user-status.php      (suspend/restore)
│   └── admin-approval.php         (approve/reject advisor)
├── client/
│   ├── dashboard.php / bookings.php / reviews.php / profile.php
├── advisor/
│   ├── dashboard.php / profile.php / availability.php / bookings.php / earnings.php
└── admin/
    ├── dashboard.php / users.php / approvals.php / reports.php
    ├── messages.php / message-view.php   Contact-page message inbox
    └── view-bar-id.php                    View an advisor's uploaded Bar Council ID
```

## How a typical action works (Post/Redirect/Get)

Example — an advisor responding to a booking request:
1. `advisor/bookings.php` renders a `<form>` per row with a hidden
   `booking_id` and Accept/Reject buttons.
2. Submitting it `POST`s to `actions/respond-booking.php`.
3. That script checks the booking belongs to the logged-in advisor,
   updates its status, sends the client a notification email, sets a
   one-time flash message in the session, and issues
   `header('Location: ../advisor/bookings.php')`.
4. The browser follows the redirect with a fresh `GET`, so refreshing
   the resulting page never re-submits anything, and the flash message
   (dismissible with the × in its top-right corner) shows once and
   disappears on the next navigation.

Every mutating action in the site (bookings, reviews, profile edits,
availability, admin actions) follows this same pattern.

## Notes

- A booking's displayed status is computed on the fly: a `confirmed`
  booking whose date/time has passed shows as **Completed** everywhere,
  without a cron job.
- **Clients cannot cancel their own bookings.** This was intentionally
  removed — `actions/cancel-booking.php` is still present but always
  responds with "not permitted," to guard against the endpoint being
  hit directly even though the Cancel button itself was removed from
  `client/bookings.php`.
- **Payment is simulated.** `payment.php` presents an SSLCommerz-style
  checkout UI (bKash/Nagad/Rocket/Upay options), but no real payment
  gateway is connected and no money is ever actually charged —
  `actions/process-payment.php` is written so a real gateway can be
  swapped in later without changing the database schema.
- All SQL uses PDO prepared statements.
- All user-supplied text is escaped with `htmlspecialchars()` (the `h()`
  helper in `includes/auth.php`) before being echoed into HTML.
- Suspending a user immediately removes their advisor listing from
  search, their profile page, the homepage counts, and blocks new
  bookings to them — restoring brings all of that back.
- Icons are [Font Awesome](https://fontawesome.com) (loaded via CDN in
  `includes/header.php`) rather than emoji, for consistent rendering
  across devices — colors are set in `assets/css/style.css` to match
  the site's gold/black theme.