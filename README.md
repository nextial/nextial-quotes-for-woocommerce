# Nextial Quotes – Request a Quote for WooCommerce

**The full quote workflow for WooCommerce, free.** Customers add products to a quote list and send a request; the request becomes a WooCommerce order with the status **"Quote request"**; you set your prices and press **"Send quote to customer"**; the customer gets an e-mail with **"Accept and pay"** (standard WooCommerce payment page, any gateway) and **"Decline"**.

Status: version 0.1.0, waiting for the first submission to WordPress.org.

## What is in the free plugin

- "Add to quote" on product pages (simple and variable products, with quantity) **and on shop and category pages**.
- Choose which products: all, or only some categories (sub-categories included), plus excluded products.
- Hide the price and/or "Add to cart" on quotable products. Hidden prices stay hidden in structured data and in the public Store API too; "Add to cart" is blocked on the server, including the Cart and Checkout blocks.
- Quote page created on activation (`[nextial_quote]`): editable list + request form (name, e-mail, phone, company → billing company, message, optional privacy checkbox).
- Spam protection: honeypot, expiring form token, requests-per-hour limit and optional **Cloudflare Turnstile** (verified on the server).
- **No session until a product is added**: browsing visitors get no cookie and no database row, so page caching keeps working.
- Requests are WooCommerce orders (HPOS and classic storage), kept out of Analytics until paid; stock is reduced only on payment.
- One-click "Send quote" from Order actions; safe "Decline" (opening the link does not decline: e-mail scanners cannot cancel a quote).
- Four editable e-mails in WooCommerce → Settings → Emails.
- **Quote for a whole wishlist**: "Request a quote for this list" button on the YITH WooCommerce Wishlist and TI WooCommerce Wishlist pages (a paid feature in both wishlist plugins).
- Works with any theme or page builder: plain HTML forms, no JavaScript required.

## Use it with ChatGPT or Claude

Every quote request is a WooCommerce order, so [Nextial Order Events](https://wordpress.org/plugins/nextial-order-events-for-woocommerce/) can alert ChatGPT, Claude or your webhooks the moment a request arrives.

## Development

Plain PHP, no build step. The end-to-end tests run in WordPress Playground (classic storage) and on MariaDB (HPOS); scripts live in the Nextial lab. Releases: a GitHub release with tag = plugin version deploys to WordPress.org (after approval).

## Other Nextial plugins

- [Nextial Order Events for WooCommerce](https://github.com/nextial/nextial-order-events-for-woocommerce) — new orders, stock and refunds to ChatGPT and Claude in real time ([WordPress.org](https://wordpress.org/plugins/nextial-order-events-for-woocommerce/))
- [Nextial Order Archive](https://github.com/nextial/nextial-order-archive) — archive old WooCommerce orders and keep the Orders screen fast ([WordPress.org](https://wordpress.org/plugins/nextial-order-archive/))
- [Nextial Form Events](https://github.com/nextial/nextial-form-events) — every form entry to ChatGPT or Claude ([WordPress.org](https://wordpress.org/plugins/nextial-form-events/))

License: GPL-2.0-or-later
