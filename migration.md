# Migrating from Aspen to KitMage

The KitMage release uses new PHP namespaces, constants, translation domains, database
table names, option names, and metadata keys. Existing Aspen data must therefore be
renamed once when upgrading. The public `/training-enroll/{token}/` URLs and the My
Account `training-entitlements` endpoint do not change, so existing links continue to
work after the data migration.

## Before you begin

1. Put the site in maintenance mode and **back up the database**.
2. Deactivate **Aspen LearnDash Training Entitlement Manager**.
3. Do not activate KitMage yet. In particular, the destination tables
   `wp_kitmage_lde_grants` and `wp_kitmage_lde_redemptions` must not already exist.
4. Replace `wp_` in every command below with the site's actual WordPress table
   prefix. On Multisite, repeat the table, option, and metadata steps for every site,
   using that site's prefix (for example, `wp_2_`).

Run the commands with a database account that can rename tables and update data.
They can be pasted into the MySQL/MariaDB client or run through a database management
tool. `RENAME TABLE` is atomic, but MySQL DDL implicitly commits, so the backup is the
rollback plan.

## Migration SQL

First confirm that both Aspen source tables exist and that neither KitMage destination
table exists:

```sql
SHOW TABLES LIKE 'wp_aspen_lde_grants';
SHOW TABLES LIKE 'wp_aspen_lde_redemptions';
SHOW TABLES LIKE 'wp_kitmage_lde_grants';
SHOW TABLES LIKE 'wp_kitmage_lde_redemptions';
```

Rename the entitlement tables together. This preserves grant IDs, redemption
relationships, bearer tokens, expiry dates, and redemption history:

```sql
RENAME TABLE
  wp_aspen_lde_grants TO wp_kitmage_lde_grants,
  wp_aspen_lde_redemptions TO wp_kitmage_lde_redemptions;
```

Rename the schema-version option:

```sql
UPDATE wp_options
SET option_name = 'kitmage_lde_db_version'
WHERE option_name = 'aspen_lde_db_version';
```

Rename product, variation, and LearnDash course metadata:

```sql
UPDATE wp_postmeta
SET meta_key = CASE meta_key
  WHEN '_aspen_lde_course_id' THEN '_kitmage_lde_course_id'
  WHEN '_aspen_lde_count' THEN '_kitmage_lde_count'
  WHEN '_aspen_lde_days' THEN '_kitmage_lde_days'
  WHEN '_aspen_lde_redirect' THEN '_kitmage_lde_redirect'
  WHEN '_aspen_lde_fluentcrm_enabled' THEN '_kitmage_lde_fluentcrm_enabled'
  WHEN '_aspen_lde_fluentcrm_tag_ids' THEN '_kitmage_lde_fluentcrm_tag_ids'
  WHEN '_aspen_lde_fluentcrm_match' THEN '_kitmage_lde_fluentcrm_match'
  WHEN '_aspen_lde_fluentcrm_next_url' THEN '_kitmage_lde_fluentcrm_next_url'
END
WHERE meta_key IN (
  '_aspen_lde_course_id',
  '_aspen_lde_count',
  '_aspen_lde_days',
  '_aspen_lde_redirect',
  '_aspen_lde_fluentcrm_enabled',
  '_aspen_lde_fluentcrm_tag_ids',
  '_aspen_lde_fluentcrm_match',
  '_aspen_lde_fluentcrm_next_url'
);
```

Rename the WooCommerce order-line snapshots. This table is used for line-item metadata
with both legacy order storage and HPOS:

```sql
UPDATE wp_woocommerce_order_itemmeta
SET meta_key = CASE meta_key
  WHEN '_aspen_lde_course_id' THEN '_kitmage_lde_course_id'
  WHEN '_aspen_lde_count' THEN '_kitmage_lde_count'
  WHEN '_aspen_lde_days' THEN '_kitmage_lde_days'
  WHEN '_aspen_lde_redirect' THEN '_kitmage_lde_redirect'
END
WHERE meta_key IN (
  '_aspen_lde_course_id',
  '_aspen_lde_count',
  '_aspen_lde_days',
  '_aspen_lde_redirect'
);
```

## Finish and verify

1. Install this release so that the plugin entry file is
   `kitmage-lms-entitlement-manager.php`; remove the old Aspen entry file rather than
   leaving two copies installed.
2. Activate **KitMage LearnDash Training Entitlement Manager**. Activation validates
   the schema and refreshes WordPress rewrite rules.
3. Open a configured product and a course with FluentCRM requirements and confirm the
   saved settings are present.
4. In **My Account > Training Entitlements**, confirm historical grants and redeemed
   users appear. Test an unused pre-migration enrollment link.
5. Check that no old keys remain:

```sql
SELECT option_name FROM wp_options WHERE option_name LIKE 'aspen\_lde\_%';
SELECT meta_key, COUNT(*) FROM wp_postmeta
WHERE meta_key LIKE '\_aspen\_lde\_%' GROUP BY meta_key;
SELECT meta_key, COUNT(*) FROM wp_woocommerce_order_itemmeta
WHERE meta_key LIKE '\_aspen\_lde\_%' GROUP BY meta_key;
SHOW TABLES LIKE 'wp_aspen_lde_%';
```

All four checks should return no rows. Once application-level verification is
complete, take a new backup and leave maintenance mode.

If KitMage was accidentally activated before migration and created empty destination
tables, deactivate it, verify those tables contain no production data, drop only the
empty destination tables, and then restart these instructions. Never drop or overwrite
destination tables that contain grants or redemptions; reconcile those records from a
backup with a database administrator instead.
