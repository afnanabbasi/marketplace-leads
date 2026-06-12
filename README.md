# Marketplace Leads

A lead marketplace for WordPress, built as a custom plugin: tradespeople (providers) register, get approved by an admin, and spend credits to unlock client leads — with the whole flow driven by a clean REST API.

> **About this repository.** This is a standalone *demonstrator*. It re-implements the core architecture I designed and shipped for a production lead-marketplace platform (a live client product) — but contains none of that client's code, branding, or data. I built it from scratch to show how I approach WordPress engineering: object-oriented, secured, and structured like software rather than a pile of snippets. The production system is private and client-owned.

## What it does

- **Provider registration** — a logged-in user opts in to become a provider and is granted starter credits, with status set to *pending*.
- **Admin approval workflow** — administrators approve or reject providers from a dedicated screen. Only approved providers can unlock leads.
- **Credit ledger** — an append-only ledger (custom DB table) tracks every credit granted or spent, so a provider's balance is always auditable.
- **Lead unlocking** — unlocking a lead's contact details debits credits atomically; insufficient balance returns a clean `402` error; repeat unlocks are idempotent and never double-charge.
- **Curated REST API** — a versioned API under `marketplace-leads/v1`, with explicit permission callbacks instead of exposing the leads through WordPress core endpoints.

## Architecture

```
marketplace-leads/
├── marketplace-leads.php        # Bootstrap: constants, autoloader, lifecycle hooks
├── uninstall.php                # Teardown on delete (drops table, removes role/options)
├── composer.json                # PSR-4 metadata (plugin also ships its own autoloader)
└── src/
    ├── Plugin.php               # Thin coordinator; wires modules to hooks
    ├── Activator.php            # Roles + custom table + default options on activation
    ├── Deactivator.php          # Non-destructive deactivation
    ├── Roles/Roles.php          # Provider role + custom capabilities
    ├── PostTypes/Lead.php       # Lead CPT, meta, and public/contact serializers
    ├── Providers/ProviderManager.php  # Provider state machine (pending → approved)
    ├── Credits/CreditLedger.php # Append-only ledger over a custom table
    ├── Rest/RestController.php  # Routes, permission callbacks, validation
    └── Admin/
        ├── AdminMenu.php        # Top-level menu wiring
        ├── Settings.php         # Settings API (unlock cost, starter credits)
        └── ProvidersPage.php    # Approve/reject UI (nonce + capability protected)
```

The design keeps each concern isolated: `RestController` never touches the database directly — it goes through `CreditLedger` and `ProviderManager`, which own their own data. Authorization is expressed as **capabilities** (`ml_unlock_leads`) rather than role-name string checks, so it stays explicit and easy to reason about.

## Engineering practices shown here

- Object-oriented design with a dependency-light PSR-4 autoloader (runs without `composer install`).
- Custom roles and capabilities for authorization.
- A custom database table created with `dbDelta`, read/written with `$wpdb->prepare()` and typed insert formats.
- A versioned REST API where **every** write route has a permission callback returning typed `WP_Error`s with correct HTTP status codes (`401`, `402`, `403`, `404`, `409`).
- Input sanitization (`sanitize_text_field`, `sanitize_email`, `absint`) and output escaping (`esc_html`, `esc_url`) throughout.
- Admin actions protected by nonces (`check_admin_referer`) and capability checks.
- Activation/deactivation/uninstall lifecycle handled cleanly and non-destructively.
- Extensibility via action hooks (`ml_provider_registered`, `ml_provider_approved`, `ml_provider_rejected`) so notifications can be added without editing core classes.

## REST API

Base: `/wp-json/marketplace-leads/v1`

| Method | Endpoint                  | Auth                          | Description                                  |
|--------|---------------------------|-------------------------------|----------------------------------------------|
| GET    | `/leads`                  | Public                        | List available leads (no contact details).   |
| POST   | `/leads`                  | Logged-in                     | Submit a new lead.                           |
| POST   | `/leads/{id}/unlock`      | Approved provider             | Spend credits to reveal a lead's contacts.   |
| POST   | `/providers/register`     | Logged-in                     | Become a provider (pending) + starter credits.|
| GET    | `/me`                     | Logged-in                     | Current provider status and credit balance.  |

### Examples

List leads:

\`\`\`bash
curl https://example.com/wp-json/marketplace-leads/v1/leads
\`\`\`

Register as a provider (cookie-authenticated request with a REST nonce):

\`\`\`bash
curl -X POST https://example.com/wp-json/marketplace-leads/v1/providers/register \
  -H "X-WP-Nonce: <wp_rest_nonce>" --cookie "<wp_auth_cookies>"
\`\`\`

Unlock a lead:

\`\`\`bash
curl -X POST https://example.com/wp-json/marketplace-leads/v1/leads/42/unlock \
  -H "X-WP-Nonce: <wp_rest_nonce>" --cookie "<wp_auth_cookies>"
# 200 -> { "lead_id": 42, "contact": { "email": "...", "phone": "..." }, "charged": 5, "balance": 15 }
# 402 -> { "code": "ml_insufficient_credits", "data": { "status": 402, "balance": 2, "cost": 5 } }
\`\`\`

## Installation

1. Copy the `marketplace-leads` folder into `wp-content/plugins/`.
2. Activate **Marketplace Leads** from the Plugins screen. Activation creates the provider role, the ledger table, and default settings.
3. Configure unlock cost and starter credits under **Marketplace → Settings**.
4. Approve providers under **Marketplace → Providers**.

No build step or Composer install is required — the plugin ships its own autoloader. `composer.json` is included for PSR-4 metadata and tooling.

## Possible extensions

This demonstrator deliberately stops at the core. In production you would add: payment top-ups (e.g. Stripe) that credit the ledger, email/WhatsApp notifications on the provider hooks, lead categories/filtering, and a database transaction around the debit to harden against concurrent unlocks.

## Author

**Afnan Abbasi** — Full-Stack Web Developer
[afnanabbasi.com](https://afnanabbasi.com) · [LinkedIn](https://www.linkedin.com/in/afnanabbasi/)

## License

MIT — see [LICENSE](LICENSE).
