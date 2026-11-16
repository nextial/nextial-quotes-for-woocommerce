=== Nextial Quotes – Request a Quote for WooCommerce ===
Contributors: nextial
Tags: request a quote, quote, hide price, b2b, woocommerce quote
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Let customers request a quote, then send prices in one click: the customer accepts and pays online, or declines. Free, with the full quote workflow.

== Description ==

Many stores cannot show one price for everything: furniture made to measure, wholesale orders, B2B, big quantities, shipping that depends on the destination. Nextial Quotes adds a complete quote workflow to WooCommerce – **in the free version**:

1. **"Add to quote" button** on product pages (simple and variable products, with quantity) **and on shop and category pages**.
2. **Choose which products**: all products, or only some categories (sub-categories included), with a list of excluded products.
3. **Hide the price and/or "Add to cart"** on quotable products. Hidden prices stay hidden everywhere: product page, shop, structured data for search engines and the public Store API used by block themes. "Add to cart" is also blocked on the server, including the Cart and Checkout blocks.
4. **Quote page created for you** with the editable list and a request form (name, e-mail, phone, company, message, optional privacy checkbox). Spam protection: hidden trap field, expiring form token, a limit of requests per hour and, if you want it, **Cloudflare Turnstile** (free keys, verified on the server).
5. **Every request becomes a WooCommerce order** with the status **"Quote request"**. You get an e-mail (reply goes straight to the customer) and the customer gets a confirmation without prices.
6. **Send the quote in one click**: open the request, change prices, add shipping or fees with the normal WooCommerce tools, then choose **Order actions → "Send quote to customer"**. The customer receives the quote with **"Accept and pay"** (the standard WooCommerce payment page, any payment method) and **"Decline"**.
7. **Accepted?** The order moves to Processing like any paid order, stock is reduced only then, and the usual WooCommerce e-mails go out. **Declined?** The order is cancelled, the reason is saved in the order notes and you get an e-mail. Opening the decline link does not decline by itself (safe against e-mail link scanners): the customer confirms with a button.
8. Quotes are kept out of WooCommerce Analytics until they are paid. Customers with an account see their quotes in My Account → Orders, with a "Pay" button.

Light on your database: no session, cookie or database row is created for a visitor until they really add a product to the quote list, so page caching keeps working. Works with any theme and page builder: plain HTML forms, no JavaScript required (except the optional Turnstile check), the list adapts to phones, shortcode `[nextial_quote]` for the quote page. Compatible with High-Performance Order Storage (HPOS) and the classic order storage.

= Use it with ChatGPT or Claude =

Every quote request is a WooCommerce order, so [Nextial Order Events](https://wordpress.org/plugins/nextial-order-events-for-woocommerce/) can alert ChatGPT, Claude or your webhooks the moment a request arrives.

== Installation ==

1. Install and activate the plugin (WooCommerce must be active).
2. A "Request a quote" page is created with the `[nextial_quote]` shortcode. Add it to your menu if you like.
3. Go to WooCommerce → Settings → Quotes and choose which products can be quoted and whether to hide prices or "Add to cart".
4. E-mails can be edited in WooCommerce → Settings → Emails ("Quote request", "Your quote", …).

== Frequently Asked Questions ==

= How do I send a quote? =

Open the order with status "Quote request" in WooCommerce → Orders. Edit the item prices (click the pencil on each line), add shipping or fees if needed, press "Recalculate", then choose "Send quote to customer" in the Order actions box and press Update. The total must be above zero.

= How does the customer pay? =

The "Accept and pay" button opens the WooCommerce "Pay for order" page with your payment methods. If the customer has an account, they log in first; guests pay with the secure link.

= Can I resend a quote after changing it? =

Yes. Change the order and choose "Send the quote again" in Order actions.

= Is the stock reduced when someone asks for a quote? =

No. Stock is reduced only when the customer pays, as for any WooCommerce order.

= Does it work with variable products? =

Yes. The customer chooses the options on the product page and adds the variation to the quote.

= How do I turn on Cloudflare Turnstile? =

Create a free Turnstile widget in your Cloudflare dashboard, then paste the Site key and the Secret key in WooCommerce → Settings → Quotes. With both keys set, the quote form shows the check and each request is verified with Cloudflare before it is saved. Leave the keys empty to use only the built-in protection.

= Does it slow down my store or fill the database with sessions? =

No. The plugin starts a WooCommerce session only when a visitor adds a product to the quote list. Visitors who only browse get no cookie and no row in the database.

== External services ==

This plugin can use **Cloudflare Turnstile**, an anti-spam check, only if you enter your own Turnstile keys in WooCommerce → Settings → Quotes. It is off by default.

* What it does: on the quote request page, the visitor's browser loads the Turnstile script from `https://challenges.cloudflare.com/turnstile/v0/api.js`, which runs the check and may collect browser and device signals. When the form is sent, your site sends the Turnstile token, your secret key and the visitor's IP address to `https://challenges.cloudflare.com/turnstile/v0/siteverify` to confirm the check.
* When: only on the quote request page and only when a request is submitted, and only while both keys are set.
* Provider: Cloudflare, Inc. [Terms of service](https://www.cloudflare.com/website-terms/), [Privacy policy](https://www.cloudflare.com/privacypolicy/), [Turnstile privacy addendum](https://www.cloudflare.com/turnstile-privacy-policy/).

No other external service is contacted.

== Screenshots ==

1. Shop and category pages: price hidden, "Add to quote" instead of "Add to cart" (only for the categories you choose).
2. Product page with quantity and "Add to quote" (simple and variable products).
3. The quote list and request form: change quantities, remove items, send the request. No account needed.
4. The e-mail your customer receives: prices, total, "Accept and pay" and "Decline".
5. The request is a normal WooCommerce order with the "Quote sent" status and the payment link for the customer.
6. Settings in WooCommerce → Settings → Quotes.

== Changelog ==

= 0.1.0 =
* First version: quote list and request form, requests as WooCommerce orders ("Quote request" / "Quote sent"), send quote in one click, Accept and pay / Decline, rules by category and excluded products, hide price and "Add to cart", four editable e-mails, optional Cloudflare Turnstile, no session until a product is added, list readable on phones.
