# Changelog

## 0.2.0

- **Org domain must be `https`**: `http://` and other schemes, paths, queries, fragments and userinfo are rejected on save and at login. Bare hosts get `https://`.
- **`groups` scope is requested only from the org authorization server.** With a custom `auth_server`, define a `groups` claim on the server's Claims tab instead (fixes `invalid_scope`).
- i18n: login-button label and config phrases in `i18n/en_US.csv`.
- Requires `dmlab/module-customer-sso` / `dmlab/module-sso-core` `^0.2.0`.
- Packaging: `dmlab/module-sso-core`, `magento/module-store` and `magento/module-config` declared, `DmLab_SsoCore` / `Magento_Config` sequenced, composer metadata, OSL-3.0 headers.

## 0.1.1

- Initial release under the `dmlab` vendor and `DmLab` namespace.
