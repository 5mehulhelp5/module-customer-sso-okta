# MageDevGroup_CustomerSsoOkta

> Okta login for the Magento 2 storefront.

![License](https://img.shields.io/badge/license-OSL--3.0-green) ![Magento](https://img.shields.io/badge/Magento-2.4-orange) ![PHP](https://img.shields.io/badge/PHP-8.3--8.5-blue) ![Version](https://img.shields.io/badge/version-0.0.1-lightgrey)

Thin Okta provider plugin for the `customer-sso` storefront SSO capability. It ships an Okta `ProviderPreset` (discovery URL, scopes, groups claim, branding) plus Okta org-domain config, and registers it into `customer-sso`'s `PresetRegistry` — all OIDC protocol and storefront logic live upstream. Installing it pulls `customer-sso` and, transitively, `sso-core`.

## Installation

```bash
composer require magedevgroup/module-customer-sso-okta
bin/magento module:enable MageDevGroup_SsoCore MageDevGroup_CustomerSso MageDevGroup_CustomerSsoOkta
bin/magento setup:upgrade
```

## Create the Okta OIDC app

In the Okta Admin Console → **Applications → Create App Integration**:

1. Sign-in method **OIDC - OpenID Connect**, application type **Web Application**.
2. **Sign-in redirect URI** — the `customer-sso` callback:
   `https://<store-host>/customersso/sso/callback`. It must match the storefront
   URL used at runtime exactly.
3. Save, then copy the **Client ID** and **Client secret**.
4. **Groups claim** — for IdP-group → customer-group mapping, add a `groups` claim to
   the ID token: **Sign On → OpenID Connect ID Token → Groups claim**, filter e.g.
   `Matches regex .*`. Without this Okta emits no groups and group mapping falls back
   to the default customer group.

## Configuration

Admin → Stores → Configuration → **MageDevGroup → Customer SSO** (store-scoped —
tune per store view).

**General** (`magedevgroup_customer_sso/general/*`):

| Field | Value |
|---|---|
| Enable Customer SSO | Yes |
| Identity Provider | Okta |
| Client ID | from the Okta app |
| Client Secret | from the Okta app |

**Okta** (`magedevgroup_customer_sso/okta/*`, shown when Okta is selected):

| Field | Value |
|---|---|
| Org Domain | your org domain, e.g. `dev-123.okta.com` (scheme optional, `https` assumed) |
| Custom Authorization Server | optional server id (e.g. `default`); empty = org server |

The discovery URL is derived from these:
`https://<domain>/.well-known/openid-configuration`, or
`https://<domain>/oauth2/<auth-server>/.well-known/openid-configuration` with a
custom authorization server.

Account linking by email, keep-password-login, and group → customer-group mapping
are configured in `customer-sso`; see that module's README.

## Design decisions

**Okta preset: duplicated, not shared.** `admin-sso-okta` ships the first Okta preset; this
is the second consumer. The preset is duplicated here rather than extracted into a shared
`sso-okta` module because:

- The shared surface is small (~40 lines: discovery-URL builder, domain normalization,
  default scopes, groups claim, button metadata).
- The two presets are not literal copies — `admin-sso-okta` reads the `magedevgroup_admin_sso`
  config section (admin scope), this plugin reads `magedevgroup_customer_sso` (store scope), and
  each ships its own logo asset. A shared preset would have to parameterize config path + asset id.
- Keeps the suite's `<capability>-<idp>` scheme: adding an IdP is one new plugin, the core is
  never touched. No extra shared repo to version and release.

Extraction stays open if the copies grow or drift (rule of three) — not yet earned at the 2nd consumer.

## Requirements

- Magento **2.4.x**
- PHP **8.3 – 8.5**

## Part of the MageDevGroup identity suite

| Repo | Role |
|------|------|
| `sso-core` | Shared OIDC engine (installed automatically) |
| `admin-sso` · `admin-sso-<idp>` | Admin-panel SSO login |
| `customer-sso` · `customer-sso-<idp>` | Storefront SSO login |
| `admin-scim` · `admin-scim-<idp>` | Admin-user provisioning (SCIM 2.0) |

## License

[OSL-3.0](LICENSE) © MageDevGroup. Commercial licensing and support: <https://magedevgroup.com>.
