=== Nextial Quotes – Request a Quote for WooCommerce ===
Contributors: nextial
Tags: request a quote, quote, hide price, b2b, woocommerce quote
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.1
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
9. **No lost requests.** The request is saved as an order before any e-mail is sent. **WooCommerce → Quote requests** shows how many requests are waiting. If WordPress cannot send the "new request" e-mail (a common problem with shared hosting), you get a warning in the dashboard with links to the requests, and a note on the order. A **"Send a test e-mail"** button in the settings checks your setup in one click.

Light on your database: no session, cookie or database row is created for a visitor until they really add a product to the quote list, so page caching keeps working. Works with any theme and page builder: plain HTML forms, no JavaScript required (except the optional Turnstile check), the list adapts to phones, shortcode `[nextial_quote]` for the quote page. Compatible with High-Performance Order Storage (HPOS) and the classic order storage.

= Nextial Quotes Pro (optional) =

Everything above is free. The optional [Nextial Quotes Pro](https://checkout.freemius.com/plugin/45147/plan/79600/?trial=free) add-on (7-day free trial, from $39 a year) adds a PDF quote attached to the e-mail, on the order and in My Account; a quote expiry date with an automatic reminder to the customer; quote mode only for guests or chosen roles, or only for out-of-stock products; and custom fields in the request form (VAT number, delivery date and more).

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

= Does it work on multilingual sites (WPML, Polylang)? =

Yes. Choose the quotable categories and excluded products in the default language: their translations follow automatically, so translated products get the quote button and hidden prices too. The "Add to quote" button points to the quote page in the visitor's language (translate the page and keep the `[nextial_quote]` shortcode in it). The button text and the privacy checkbox text can be translated in String translation (group "Nextial Quotes"). The order shows the language of the request. Tested with Polylang; WPML uses the same API. The plugin's own texts are translated through translate.wordpress.org.

= Can I create a quote for a customer myself (phone or e-mail requests)? =

Yes. In WooCommerce → Orders → Add order, add the products and prices and the customer's e-mail, set the status to "Quote request" and save. Then choose "Send quote to customer" in Order actions: the customer gets the same e-mail with "Accept and pay" and "Decline" as for requests from the form.

= I don't receive the e-mail for new quote requests. Are they lost? =

No. Every request is saved as a WooCommerce order with the "Quote request" status before any e-mail is sent: find them in WooCommerce → Quote requests. If WordPress reports that the e-mail failed, Nextial Quotes shows a warning in the dashboard and adds a note to the order. Use "Send a test e-mail" in WooCommerce → Settings → Quotes: if the test does not arrive, your site cannot deliver e-mail and an SMTP plugin usually fixes it. Note: some hosts accept the e-mail and lose it later; WordPress cannot see that, so check the Quote requests counter too.

= Does it break page caching (Cloudflare, WP Rocket, LiteSpeed Cache…)? =

No. A visitor who only browses gets no cookie, no PHP session and no `Cache-Control: no-store` header from Nextial Quotes, and no session row is written to the database, so your home, shop, category and product pages stay cacheable. The WooCommerce session cookie is created only when the visitor really adds a product to the quote list (like the cart does). We test this on every release: 12 anonymous page views with zero cookies and zero new sessions, with HPOS and with the classic order storage, also with Nextial Quotes Pro active.

= Does it work with Elementor? =

Yes, with Elementor and the Hello Elementor theme. To build the quote page with Elementor, add a Shortcode widget with `[nextial_quote]`. We test the whole flow (add to quote, request form, quote e-mail, payment) with Elementor 4.4.1 and Hello Elementor 3.5.2. Product templates made with Elementor Pro are not tested yet: if one hides the quote button, tell us in the support forum.

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

= 0.1.1 =
* No lost requests: "Quote requests" counter in the WooCommerce menu, dashboard warning and order note when the new-request e-mail cannot be sent, "Send a test e-mail" button in the settings.
* Multilingual sites (WPML, Polylang): translated products and categories follow the quote rules, quote page in the visitor's language, translatable button and privacy texts, language of the request on the order.
* If the quote e-mail to the customer cannot be sent, the order screen says so (with what to do) instead of "Quote sent".
* Tested with Elementor 4.4.1 and the Hello Elementor theme (quote page built with the Shortcode widget).
* Cache-friendly, tested: browsing visitors get no cookie, no PHP session and no "no-store" header, so page caches (Cloudflare, WP Rocket, LiteSpeed Cache) keep working.
* Settings and Plugins screen: link to the optional Nextial Quotes Pro add-on (7-day free trial). Nothing in the free version is limited.
* Quotes you create yourself in Orders → Add order (status "Quote request") now work like requests from the form: quote box on the order screen, and with Nextial Quotes Pro the PDF, the expiry date and the reminder.

= 0.1.0 =
* First version: quote list and request form, requests as WooCommerce orders ("Quote request" / "Quote sent"), send quote in one click, Accept and pay / Decline, rules by category and excluded products, hide price and "Add to cart", four editable e-mails, optional Cloudflare Turnstile, no session until a product is added, list readable on phones.
