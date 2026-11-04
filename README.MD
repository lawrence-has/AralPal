# AralPal — Tutor Booking and Learning Platform

## What this project is
AralPal is designed to connect students with tutors. Students can browse tutor profiles, compare subjects/rates/ratings, book available schedules, choose a subscription plan, and review tutors after completed sessions. Tutors manage their profile and bookings. Administrators manage the platform.

## Main user flow

### Student
1. Opens the public homepage and browses available tutors.
2. Registers as a Student and logs in.
3. Searches tutors by subject, price, rating, and availability.
4. Opens a tutor profile and chooses an available schedule.
5. Submits a booking. The booking is saved with a pending status.
6. The tutor accepts or declines the request. The student can see the status in My Bookings.
7. The student chooses a subscription plan and checks out through PayMongo test mode.
8. After the gateway confirms payment, the system should save the payment and activate the subscription.
9. After a completed tutoring session, the student can submit a rating and review.

### Tutor
1. Registers as a Tutor and logs in.
2. Completes the tutor profile, adds subjects, hourly rate, and availability.
3. Receives student booking requests.
4. Accepts or declines each request.
5. Marks a session completed after the tutoring session.
6. Reviews bookings, earnings, and student feedback from the tutor dashboard.
7. Can choose the Tutor Pro plan.

### Administrator
1. Logs in with an administrator account.
2. Uses the main admin pages to manage users, tutor listings, subjects, plans, and reports.
3. Uses the CMS workspace to edit selected homepage content and publish announcements.
4. Reviews booking, subscription, and payment summaries.

## Important distinction about the CMS
The paper specifies **Strapi** as the headless CMS. The included `admin/` CMS workspace in this PHP project is a lightweight custom PHP CMS, not Strapi. It currently manages homepage text and announcements. The main PHP admin tools separately manage users, tutors, subjects, plans, and reports. If the instructor requires Strapi specifically, this custom panel must be replaced or connected to a real Strapi instance.

## Installation in Laragon
1. Back up your existing `C:\laragon\www\AralPal` folder and database first.
2. Extract this project into `C:\laragon\www\AralPal`.
3. Start Apache and MySQL in Laragon.
4. Open phpMyAdmin and create/select a database named `aralpal`.
5. Import `database/full_schema.sql` into `aralpal` for the tutor-booking system tables.
6. Import `database/cms_schema.sql` into the same `aralpal` database for the CMS tables.
7. Open `includes/config.php` and set `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASS` to match your MySQL setup.
8. Copy `admin/cms-config.example.php` to `admin/cms-config.php`. Set `CMS_DB_HOST`, `CMS_DB_NAME`, `CMS_DB_USER`, and `CMS_DB_PASS` to the same database credentials.
9. Visit `http://localhost/AralPal/admin/setup-admin.php` once to create the separate CMS administrator account.
10. Delete `admin/setup-admin.php` after creating the account.
11. Open `http://localhost/AralPal/`.

## Payment and email setup
- PayMongo uses test mode. Configure the API keys and callback/return URLs in `includes/paymongo.php` before testing checkout. Do not use live keys for a classroom demo.
- SMTP credentials are not configured by default. Update the mailer settings in `includes/mailer.php` with an email provider's app password or environment-based secret before testing email delivery.
- Test payment flow with PayMongo test credentials only. Do not enter real card details.

## Database notes
The full schema contains relational tables for roles, users, tutors, subjects, tutor-subject links, availability, bookings, subscription plans, subscriptions, payments, reviews, password resets, favorites, and notifications. The payments table includes plan and created-at fields used by checkout and reports.

## Known limitations / verify before presentation
- This is a student project build. Test every registration, booking, subscription, payment callback, email, and admin action on your own Laragon/MySQL setup before presenting.
- PayMongo and SMTP need valid test credentials and correct callback configuration.
- The custom PHP CMS is not the Strapi CMS named in the paper.
- Verify that plan booking limits are enforced consistently, overlapping tutor bookings are blocked, and booking cancellation rules match the paper.
- No live video tutoring or native mobile application is included, consistent with the paper's out-of-scope items.
- Do not deploy publicly without a security review, HTTPS, secure secret storage, backups, and production payment configuration.


## UI refinement update
- The AralPal logo is constrained to a small, consistent size in the public navigation and CMS sidebar.
- The homepage layout has responsive adjustments for tablet and mobile widths.
- Keyboard focus indicators were added to common interactive controls.
- The project PHP files were checked with `php -l`; no syntax errors were reported during this packaging pass.

These checks do not replace running the application against your own Laragon/MySQL database. Test registration, login, tutor profile editing, booking conflicts, cancellation, subscription checkout, payment callbacks, and email delivery before presenting.
