=== Aspen LearnDash Training Entitlement Manager ===
Contributors: aspen
Tags: woocommerce, learndash, subscriptions, training
Requires at least: 6.2
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later

Sell reusable LearnDash enrollment entitlements as independent WooCommerce order-line tranches.

== Dependencies ==

WooCommerce and LearnDash are required. WooCommerce Subscriptions is optional and is detected through its public APIs and successful-renewal hook. Missing required dependencies produce an administrator notice instead of a fatal error.

== Setup and product configuration ==

1. Install and activate the plugin. Activation creates the database tables and rewrite endpoints.
2. Edit a simple product (including a simple subscription) and use General > Training Entitlements. For a variable product, configure each variation.
3. Select a published or draft LearnDash course, enter a non-negative whole-number entitlement count and validity in days, and optionally enter a redirect URL. Blank or zero entitlement count disables grants. Blank or zero validity never expires.

The course, per-unit count, validity, and redirect are copied to order-line metadata during checkout. They are therefore historical transaction terms rather than live product settings. WooCommerce Subscriptions copies line metadata into subscription and renewal line items, so renewals retain the subscribed terms.

== Lifecycle ==

A paid processing/completed order creates one uniquely-tokenized grant per qualifying line item. Quantity multiplies the configured count. Database uniqueness on order item makes overlapping payment hooks idempotent. Each successfully paid renewal order creates a separate grant. Failed or merely scheduled payments do not. Cancelling a subscription does not affect paid grants.

When an originating order is cancelled or fully refunded, its active grants are marked revoked. Existing redemptions and LearnDash access are never removed.

Purchasers find orders and grant details under My Account > Training Entitlements. Usable bearer links are hidden when expired, exhausted, or revoked. Redeemer name snapshots remain visible.

== Redemption flow ==

The attendee visits `/training-enroll/{token}/`. Logged-out attendees use the normal account/login flow and return to the link. GET only renders a challenge. A nonce-protected POST locks the grant row in an InnoDB transaction, revalidates it, reserves one unique user redemption, calls `ld_update_course_access()`, verifies access, and commits. Failure rolls back the database reservation. Existing LearnDash access is not charged.

Treat enrollment links as secrets: anyone possessing a usable link may authenticate and claim one entitlement.

== Optional FluentCRM course access gate ==

Course editors can use the **FluentCRM Access Requirements** section on a LearnDash course's **Settings** tab to add a read-only tag authorization requirement. Check **Require FluentCRM tags for course access**, select one or more existing FluentCRM tags, and choose a matching rule:

* **ALL** requires the learner's FluentCRM contact to possess every selected tag.
* **ANY** requires the contact to possess at least one selected tag.

This gate only restricts an access result that LearnDash already allowed. Passing the tag rule never enrolls a learner and never changes a denied LearnDash result into access. The gate also covers direct LearnDash lesson, topic, quiz, and other course-step checks by resolving their owning course.

An enabled rule fails closed when no tags are configured, a selected tag has since been deleted, the WordPress user has no FluentCRM contact, or FluentCRM/the required API is unavailable. Missing saved tags remain visible in the editor. Administrators have no learner-facing bypass and must meet the same tags.

Checks use FluentCRM's in-process `FluentCrmApi('tags')->all()`, `FluentCrmApi('contacts')->getContactByUserRef()`, and contact `hasAnyTagId()` APIs. Results are memoized only for the current PHP request, so adding or removing a tag changes access on the next request. The integration never creates contacts or tags and never enrolls, unenrolls, or modifies LearnDash progress, completions, quizzes, or certificates. Restoring a tag restores access to the learner's unchanged course state.

== Database ==

`{prefix}aspen_lde_grants` stores immutable purchase snapshots, capacity, status, token, exact UTC issue/expiry timestamps, and origin IDs. It uniquely indexes order-item ID and token. `{prefix}aspen_lde_redemptions` stores user ID, historical display name, and UTC redemption timestamp, with a unique `(grant_id,user_id)` index. Data is retained on deactivation. Schema version is stored in `aspen_lde_db_version` and upgraded with `dbDelta()`.

== Testing ==

Run syntax checks with:

`find . -name '*.php' -print0 | xargs -0 -n1 php -l`

Run the dependency-free FluentCRM authorization unit harness with:

`php tests/test-fluentcrm-access.php`

Integration acceptance tests require a WordPress test/site fixture with WooCommerce and LearnDash; renewal cases additionally require WooCommerce Subscriptions. Exercise payment retries, refund/cancellation, exact expiry boundaries, duplicate users, and two concurrent POSTs against a one-seat grant.

The unit harness covers disabled rules, positive-result composition, preservation of an existing denial, single-tag checks, ANY and ALL matching, missing contacts, missing FluentCRM, empty rules, deleted tags, owning-course resolution for direct steps, request memoization, match whitelisting, and tag-ID normalization. In a staging site, additionally verify the course editor UI and direct course, lesson, topic, and quiz requests using the installed LearnDash and FluentCRM versions. Remove and restore a required tag and confirm access changes without enrollment or progress changes; repeat with an administrator learner account to confirm there is no role bypass.

== Changelog ==

= 1.1.0 =
* Add optional fail-closed FluentCRM ANY/ALL tag authorization for courses and course steps.

= 1.0.0 =
* Production entitlement tranche lifecycle, enrollment flow, account UI, subscriptions integration, and database schema.
