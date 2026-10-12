# AI Shop - PHP + MySQL e-commerce with lightweight AI

PHP 8+, MySQL 8+, mysqli, Bootstrap 5, vanilla JS. No frameworks, no Composer, no external AI API.

## Setup (XAMPP / WAMP)
1. Copy the `ai-shop` folder into `C:\xampp\htdocs\` (or `/opt/lampp/htdocs/`).
2. Start **Apache** and **MySQL** in the XAMPP control panel.
3. Open http://localhost/phpmyadmin -> **Import** -> choose `database.sql` -> Go. (It creates `ecommerce_db`.)
4. Check credentials in `config/db.php` (defaults: localhost / root / empty password / ecommerce_db).
5. Sample product images are already in `assets/img/` (simple generated placeholders). Replace with real photos using the same filenames. Missing files fall back to placehold.co (needs internet).
6. Open http://localhost/ai-shop/ . Open the folder in VS Code to edit.

## Smoke-test checklist
1. Home page loads: hero, Trending (8 products), categories.
2. Products page lists all 14 products; `products.php?q=watch` filters.
3. Click each category: only that category's products show; `?cat=999` shows the empty state.
4. Product page shows details; `product.php?id=9999` shows a 404 alert.
5. Visit a product, then check `product_views` in phpMyAdmin: a new row (user_id NULL for guests, session_id set).
6. Click Add to Cart: toast "Added to cart" and the navbar badge increases.
7. Cart page: change quantity (capped at stock), remove an item, subtotal/total correct.
8. Guest cart: add items while logged out; cart works; Checkout redirects to login.
9. Register a new user: guest cart items are still in the cart afterwards (migration), navbar shows your name.
10. Logout, then Login with wrong password -> "Invalid credentials"; correct password works; `login.php?redirect=https://evil.com` falls back to index.php.
11. Checkout: enter an address, Place Order -> orders.php?success=1; stock decreased in `products`; cart emptied.
12. Orders page: order with status badge, items, line totals, address.
13. Type "head" in the search box: suggestions appear after ~300 ms and link to product pages.
14. Chatbot: try "hello", "show me headphones", "cheap", "deals", "recommend", "laptop", "asdf".
15. Similar products: product page shows up to 4 products from the same category.
16. Personalized: log in, view 3-4 Electronics products, return to Home -> "AI Recommended For You" shows your viewed products; chat "recommend" suggests unviewed Electronics items; `api/ai_recommend.php?type=personalized` returns JSON.

## Troubleshooting
**Blank page** - (1) PHP fatal error with display_errors off: check `xampp/apache/logs/error.log`; (2) wrong folder/URL (use /ai-shop/); (3) PHP < 8 or missing mysqli. Fix: enable `extension=mysqli` in php.ini, restart Apache, temporarily set `display_errors=On`.

**HTTP 500** - (1) syntax/typo from edits; (2) mysqli/mysqlnd missing (`get_result` needs mysqlnd); (3) file permissions/.htaccess. Fix: read error.log, run `php -l file.php`.

**Database connection failure** - (1) MySQL not running; (2) wrong credentials in config/db.php (MySQL root may have a password); (3) database.sql not imported. Fix: start MySQL, confirm `ecommerce_db` exists in phpMyAdmin, update credentials.

**"Headers already sent"** - (1) whitespace or BOM before `<?php` (save files as UTF-8 *without BOM*); (2) output/echo before `header()`; (3) closing `?>` followed by blank lines. Fix: keep redirects before including header.php, as the provided pages do.

**JSON parse errors in JS / "Unexpected token <"** - (1) the API returned an HTML error page (PHP error, wrong path, 404); (2) warnings printed before JSON; (3) page opened from a sub-folder URL so `api/...` resolves wrong. Fix: open `http://localhost/ai-shop/api/cart_action.php?action=count` directly and read the output; check DevTools -> Network -> Response.

**Cart count not updating** - (1) API path wrong or JSON invalid; (2) JS not loaded (main.js 404 or JS error in Console); (3) cookies blocked so the session changes every request. Fix: check Console/Network, allow cookies for localhost.

**Images not loading** - (1) file missing/misspelled in `assets/img/` (names must match DB, lowercase); (2) offline, so the placehold.co fallback can't load; (3) wrong base URL. Fix: compare `products.image` with files; use the right URL.

**Guest cart not migrating on login/register** - (1) session cookie lost/cleared between pages; (2) products out of stock (skipped on purpose); (3) you logged in on a different browser/session. Fix: add to cart as guest, then log in in the same browser; inspect `$_SESSION['guest_cart']`.

**Product detail page not finding products** - (1) invalid or missing `?id=`; (2) database.sql not imported/no rows; (3) product deleted or category missing (the page joins categories). Fix: check `SELECT * FROM products` in phpMyAdmin.

## Optional improvements
Admin dashboard, product CRUD, image upload management, real LLM integration (OpenAI or others), semantic/vector search, Razorpay/Stripe payments, wishlist, coupons, pagination, product reviews, inventory alerts, order confirmation emails, CSRF tokens + rate limiting, better logging/error pages, more mobile refinements.
