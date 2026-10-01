# oxid2fa: two-factor authentication for OXID eShop administrators

TOTP (RFC 6238) as second step of the admin login, with recovery codes, audit log, brute-force throttling and
an administrative reset. Built on the shop's own authentication, without core changes.

Requirements: OXID eShop CE 7.3, PHP 8.2+, `ext-sodium`.

See [docs/architecture.md](docs/architecture.md) for setup, architecture and security decisions.

## Setup

```bash
composer require dazi-web/oxid2fa
vendor/bin/oe-eshop-doctrine_migration migrations:migrate oxid2fa
vendor/bin/oe-console oe:module:activate oxid2fa
```

1. **Activate** the module. It creates its encryption key itself (`var/oxid2fa/encryption.key`, outside the document root).
2. **Settings:** Admin → Extensions → Modules → oxid2fa → Settings: choose *2FA für Administratoren* = optional or mandatory.
   The top of that page shows whether the key is fine.
3. **Done.** Admins set up their authenticator app at *Service → 2FA – Mein Konto*; with mandatory mode they are led through
   the setup at their next login.

Back up `var/oxid2fa/encryption.key` together with the database and keep it out of version control. Without it the
stored secrets cannot be read and the affected accounts have to be reset. Details: [docs/architecture.md](docs/architecture.md).

## Other ways to sign in

Administrators who owe a second factor (2FA active, required for the account, or mandatory mode) are refused when they
sign in with a password alone anywhere but the admin area, for example through the shop front end or the GraphQL API
(`2FA_LOGIN_REFUSED` is logged). Use a separate account for API access.

## Quality tools and tests

```bash
composer static            # phpcs (PSR-12), phpstan level 8, phpmd: all three must be clean
composer tests-unit        # no shop, no database
composer tests-database    # real MariaDB/MySQL (OXID2FA_TEST_DB_* variables), no shop; includes parallel-process tests
```

## License

MIT, see [LICENSE](LICENSE).
