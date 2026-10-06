# CHANGELOG for v3.0.0

This changelog consists of the bug & security updates.

## **v3.0.0 (6th of October 2026)** - *Release*

- The suite now targets **Bagisto v2.5** on Laravel 13 and PHP 8.4. The v2.0 line remains the one to install on Bagisto v2.4.

- Rebuilt the suite's two stylesheets on **Tailwind CSS 4**. Bagisto 2.5 removed the core themes' `tailwind.config.js`, which the suite used to read, so each entry stylesheet now carries its own configuration and borrows the core theme's tokens through `@reference` — a token still resolves to the same value in a B2B view as in a core one.

- Dropped the storefront cart page override. It carried no B2B content and had fallen behind core, silently reverting the channel logo's alt text, the cart item image alt and the item remove action. The suite's quote button reaches the cart through the existing render event instead.

- Rebased the remaining view overrides on their Bagisto 2.5 originals, picking up the cart summary's tax breakdown, the wishlist setting on the cart and the account navigation's current markup.

- Fixed the company, quotation, purchase order, credit and user listings building names with MySQL-only SQL, which failed on PostgreSQL. They now use the database grammar's `CONCAT_WS`.

- Fixed the date of birth on the company profile rendering as a full timestamp, after Bagisto 2.5 began casting it to a date.

- Fixed the password toggle on the company sign-in page and the phone label on the admin company form rendering as raw translation keys, both of which Bagisto 2.5 moved.
