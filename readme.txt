=== WPIntegrations Form Connector for MailerLite ===
Contributors: wpintegrations
Tags: mailerlite, contact form 7, wpforms, gravity forms, newsletter
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Send Contact Form 7, Elementor Pro, WPForms, Fluent Forms and Gravity Forms submissions to MailerLite as subscribers, in the groups you choose.

== Description ==

**WPIntegrations Form Connector for MailerLite** adds every form submission to your MailerLite subscribers — from whichever form plugin your site uses — and puts the subscriber in the groups you choose.

= Supported form plugins =

* Contact Form 7
* Elementor Pro Forms
* WPForms (Lite and paid editions)
* Fluent Forms
* Gravity Forms

One plugin covers them all: each integration picks a form plugin, then a form, then the MailerLite fields it fills.

= Why this plugin =

* **MailerLite's built-in fields** — map your form's fields to the subscriber's email, name, last name, company, phone, country, state, city and zip. No renaming form fields to match MailerLite.
* **Name parts** — a WPForms, Fluent Forms or Gravity Forms name field offers first and last name separately, so they land in the right MailerLite fields.
* **Groups** — add every subscriber from a form to one or more MailerLite groups, picked from your account's own groups. Groups a subscriber is already in are kept.
* **Consent checkbox** — map your form's consent box, and only people who tick it are added.
* **Double opt-in** — new subscribers can start as unconfirmed, so MailerLite emails them a confirmation link; or follow your MailerLite setting.
* **Doesn't hold up your form** — on hosts running PHP-FPM or LiteSpeed (most of them), delivery happens after the visitor already has their confirmation, so a slow API doesn't slow your form down.
* **Full delivery log** — see every request, response and failure. Nothing is silent.
* **Secure by design** — your API token is stored encrypted, every admin endpoint requires administrator capability, and nothing is sent anywhere except MailerLite's API.

= What this free version includes =

* Unlimited integrations, across all five form plugins
* Email, name, last name, company, phone, country, state, city and zip
* A consent checkbox that decides whether the person is added
* New subscribers as active or unconfirmed (double opt-in), or following your MailerLite setting
* Add subscribers, or update the ones that already exist, identified by email
* Add subscribers to any number of MailerLite groups
* Optionally resubscribe people who had unsubscribed, for forms that ask for consent
* A delivery log with the full request and response for every send

= Pro version =

A separate Pro plugin, sold on [wpintegrations.net](https://wpintegrations.net/plugins/), adds:

* Your MailerLite custom fields, with numbers and dates sent in the format MailerLite expects
* Smart values resolved at submission time — page URL, submission time, the logged-in user's name and email, site title and URL — and fixed values
* The visitor's IP address and sign-up time recorded on new subscribers, as proof of sign-up
* Groups chosen by the visitor's answers
* Removing subscribers from groups, or unsubscribing them, from a form
* Conditions — send only when the answers match
* A delivery queue that retries failed sends automatically, and Resend for failed deliveries in the log

= Source code and third-party libraries =

The complete, human-readable source of this plugin is public at [github.com/wpintegrations/wpintegrations-connector-for-mailerlite](https://github.com/wpintegrations/wpintegrations-connector-for-mailerlite).

The admin screens are a React app. The compiled bundle in `build/assets/` is generated with Vite from the source in `resources/js/`, which ships with the plugin together with `package.json` and `vite.config.mjs`. To rebuild it, install Node.js 18 or later and pnpm, then run `pnpm install` and `pnpm run build` in the plugin folder.

The bundle includes these open-source libraries:

* React and React DOM 18.3.1 (MIT) — [github.com/facebook/react](https://github.com/facebook/react)
* Ant Design 5.29.3 (MIT) — [github.com/ant-design/ant-design](https://github.com/ant-design/ant-design)
* Ant Design Icons 5.6.1 (MIT) — [github.com/ant-design/ant-design-icons](https://github.com/ant-design/ant-design-icons)

This plugin is built by WPIntegrations and is not affiliated with, endorsed by, or sponsored by MailerLite, Contact Form 7, Elementor, WPForms, Fluent Forms or Gravity Forms. All names are the trademarks of their respective owners, used here only to describe what the plugin connects.

= Requirements =

* At least one of the supported form plugins (Elementor forms need Elementor Pro)
* A MailerLite account and an API token (MailerLite → Integrations → MailerLite API)

== External services ==

This plugin connects to the **MailerLite API** (operated by MailerLite, https://www.mailerlite.com/). Nothing is sent until you save an API token, and form data is sent only when a form that has an integration is submitted.

* **What is sent:** the email and the form fields you explicitly map, the groups you chose for the integration, the status you chose for new subscribers, and — when you turn it on — the request to resubscribe someone who had unsubscribed. With "Update subscribers that already exist" off, or a status chosen for new subscribers, the email is first looked up in your subscribers. Nothing is sent when a mapped consent box is left empty.
* **What is read:** your groups (so you can pick them, and to confirm the connection).
* **Where:** `https://connect.mailerlite.com/api/subscribers`, `https://connect.mailerlite.com/api/subscribers/{email}` and `https://connect.mailerlite.com/api/groups`, authenticated with your own API token.

No data is sent to WPIntegrations or any other third party. MailerLite terms of use: https://www.mailerlite.com/legal/terms-of-service — privacy policy: https://www.mailerlite.com/legal/privacy-policy

== Installation ==

1. Install and activate the plugin.
2. In MailerLite, create an API token under **Integrations → MailerLite API**. Paste it on the plugin's **Settings** tab, save, and use **Test connection**.
3. Open the **Integrations** tab and create an integration: pick the form plugin and the form, map the fields, choose groups, and save.

== Frequently Asked Questions ==

= Which fields do I have to map? =

**Email** — MailerLite identifies the subscriber by it. Every other field is optional. A submission with an empty or invalid email is not sent, and the log says why.

= What happens when the same person submits twice? =

With "Update subscribers that already exist in MailerLite" on (the default), the subscriber's mapped fields are updated and the groups are added; fields the form leaves empty and groups they are already in are kept. With it off, a returning email is left as it is in MailerLite, and the log says so.

= Does it support double opt-in? =

Yes. In MailerLite, turn on "Double opt-in for API and integrations" (Account settings → Subscribe settings). In the integration, set new subscribers to "Unconfirmed", or leave it on "Follow my MailerLite double opt-in setting". MailerLite then emails new subscribers a confirmation link. People already in MailerLite keep their status.

= How do I only add people who ticked the consent box? =

Map your consent checkbox to "Consent — only add the person when this is ticked". A submission where it is left empty is not sent, and the log says why.

= Which MailerLite fields can I map? =

Email, name, last name, company, phone, country, state, city and zip — the fields every MailerLite account has. Your own custom fields are mapped with the Pro version.

= Someone unsubscribed and filled in the form again, but they are still unsubscribed =

That is MailerLite protecting their choice. If your form clearly asks for consent to email them, turn on "Resubscribe people who had unsubscribed" in the integration.

= Does it work with the free version of Elementor? =

The Forms widget is an Elementor Pro feature, so Elementor Pro is required for Elementor forms. The other form plugins work in their free editions, except Gravity Forms, which is paid only.

= What happens if MailerLite is temporarily unavailable? =

The submission is not delivered, and the failure is recorded in the Logs tab with the full response.

= Where is the source code of the admin screens? =

In `resources/js/` inside the plugin, and publicly at [github.com/wpintegrations/wpintegrations-connector-for-mailerlite](https://github.com/wpintegrations/wpintegrations-connector-for-mailerlite). The compiled files in `build/` are generated from it with Vite: run `pnpm install` and then `pnpm run build` in the plugin folder. See "Source code and third-party libraries" above for the bundled libraries.

= Where do I see errors? =

The **Logs** tab shows every delivery with the full request and response.

== Changelog ==

= 1.0.0 =
* Initial release: Contact Form 7, Elementor Pro, WPForms, Fluent Forms and Gravity Forms submissions to MailerLite subscribers and groups, with MailerLite's built-in fields, a consent checkbox, double opt-in, a delivery log and encrypted API token storage.
