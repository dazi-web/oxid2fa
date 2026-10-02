# Architecture and operation

TOTP (RFC 6238) as second step of the admin login. The shop keeps doing username, password, rights and
sessions; this feature only decides whether a verified password is enough and, if not, holds the login back.

## Enabling

1. Activate the module. The activation event creates the encryption key: `var/oxid2fa/encryption.key` (32 random
   bytes, base64, directory `0700`, file `0600`, never overwritten). The file is outside the document root and outside
   the database that holds the encrypted secrets. Back it up with the database; keep it out of version control.
   Instead of the file you can provide the key as environment variable `OXID2FA_ENCRYPTION_KEY` (base64, 32 bytes);
   it takes precedence. Use that where `var/` is not persistent (rebuilt containers).

   The module settings page shows a key check: where the key comes from, whether the file is protected, and whether all
   stored secrets can be decrypted with it.

   Without a valid key nobody can set up or check a second factor. The setting *Wenn der Schlüssel fehlt*
   decides what happens then (every admin login writes a `2FA_NOT_OPERATIONAL` audit entry, so monitor for it):

   * **Admins mit 2FA blockieren** (default): admins who already have 2FA cannot log in (password alone
     is not accepted); all other admins log in as before. Restore the key, or reset the account with the CLI.
   * **Nur mit Passwort anmelden lassen**: nobody is locked out, but 2FA is silently off.

   **Losing or changing the key makes all stored TOTP secrets unreadable**: restore it from the backup, or reset the
   affected accounts.
2. Module settings → *Zwei-Faktor-Authentifizierung*: *2FA für Administratoren* = off / optional (default) / mandatory.
3. Admins set up their own 2FA under *Service → 2FA* (section *Mein Konto*). A second factor can only be set up by its owner (the secret
   must end up on their device), but main administrators can **require** it per account: the section *Administratoren* of the same page
   (and the user tab *2FA*) lists everyone with status and a "Verpflichtend machen" button. A required account
   is led into the setup at its next login even while the shop mode is *optional*. The same table offers the reset;
   nobody can reset their own account there (use *2FA deaktivieren*, which asks for a code). With *mandatory*, admins without 2FA are led into
   the setup right after the password check and cannot reach anything else.

Reset for a lost device and lost recovery codes: user administration → tab *2FA* (main admins only), or

```bash
vendor/bin/oe-console oxid2fa:reset <login name>
```

Audit entries (`2FA_ENABLED`, `2FA_DISABLED`, `2FA_RESET`, `RECOVERY_CODES_REGENERATED`, `RECOVERY_CODE_USED`,
`2FA_CHALLENGE_FAILED`, `2FA_CHALLENGE_LOCKED`, `2FA_UNLOCKED`, `2FA_LOGIN_REFUSED`, `2FA_NOT_OPERATIONAL`, `2FA_REQUIRED_SET`, `2FA_REQUIRED_CLEARED`) go to `oxid2fa_audit.log` in the shop's log directory.
They contain user ids and the origin of the request (`ip` = `REMOTE_ADDR`; `forwarded_for` = the first address of
`X-Forwarded-For`, only if it is a valid IP and differs, because that header can be forged), never codes or secrets.
IP addresses are personal data: keep the log under your retention rules.

The latest events are shown in the admin: *Service → 2FA*, section *Protokoll* (main admins only; the list above it shows
all accounts, with the failed attempts per account and a button to release a lock) and in the user tab *2FA* (that account only). Only the last 512 KB of the file
are read, and old files are not rotated by the module: rotate `oxid2fa_audit.log` with your usual log rotation.

## How it hooks in

`User::login()` writes the admin login into the session variables `auth` and `login-token`. Every back end
entry point (`AdminController::authorize()`, `oxajax.php`) only checks `auth` through `Utils::checkAccessRights()`.

`Controller\Admin\LoginController` (chain extension of the core admin login) calls `TwoFactorGate` right after
`checklogin()`. The gate removes `auth`/`login-token` from the session *first*, then decides:

* not required → they are put back, the flow is unchanged;
* required → a pending login (user, step, start time) is stored instead. Without `auth`, no admin controller,
  AJAX call or direct URL accepts the visitor, so enforcement is server side and by default-deny.
* a correct code (or finished setup) restores `auth`, rotates the session id and clears the pending state.

Pending logins expire after 15 minutes.

**Other ways to sign in with a password.** The shop front end, the GraphQL API and other modules also end in
`User::login()`, but have no place for a second code. The module extends `User::onLogin()` (it runs after the password
was accepted and before the shop writes the login into the session): outside the admin area, an administrator who owes a
second factor is refused with the shop's usual "invalid login" message, and `2FA_LOGIN_REFUSED` is logged. This
covers anyone who is an administrator and has 2FA, is required to have it, or cannot get it because mandatory mode is on.
Customers are not affected. The consequence: such an account cannot use the API or the front end with its password alone.

For the GraphQL API there is the query `twoFactorLogin(username, password, code)`
(`Integration/GraphQL/Controller/TwoFactorLogin`). It hands the code to `SecondFactorSubmission` and calls the base
module's login service; `PasswordOnlyLoginGuard` then checks the code inside `onLogin()` with the same
`ChallengeService` as the admin login, so attempt limit, replay protection and recovery codes apply. The code is
consumed there, so it cannot leak into another login of the same request. The query is registered with a service tagged
`graphql_namespace_mapper`. The shop builds every service of a module when the module is activated, also without the
GraphQL base module; that is why the mapper is created by `NamespaceMapperFactory` (touches the base module's interface
only if it exists) and the controller takes its login service as optional. The module is a dev dependency and a
`suggest`, not a requirement.

```
Admin Login → password (core) → TwoFactorGate → SecondFactorDecision
                                   ├─ not required ───────────────→ logged in
                                   └─ required: auth withheld → challenge / setup
                                                  → code ok → session rotation → logged in
```

## Layout

| Folder | Content |
|---|---|
| `Domain` | Enums and value objects. No OXID dependency |
| `Application` | The use cases and the ports they need (`LoginSession`, `UserDirectory`, repositories) |
| `Infrastructure` | DBAL repositories, throttle, libsodium `SecretCipher`, key file, audit log reader |
| `Integration/Oxid` | Session, user lookup, settings, `ViewConfig` and `User` extensions: the only code that knows OXID internals |
| `Controller`, `Command` | Login hook, challenge page, security page, admin overview, user tab, CLI |

The main use cases: `SecondFactorDecision` (does this administrator owe a second factor, and which step?), `TwoFactorGate` (the admin login), `PasswordOnlyLoginGuard` (every other password login), `TwoFactorLoginFlow` (the waiting
login), `ChallengeService` (checks a code against the attempt budget), `SetupService`, `EnrollmentService`,
`TotpVerifier`, `RecoveryCodeService`, `AdminOverview`, `AuditReport`, `KeyHealthChecker`.

## Decisions

* **Secret storage**: OXID 7.3 has no secret store (the core `Encryptor` is an XOR obfuscation). Secrets are
  encrypted with libsodium `secretbox`; the key comes from the environment or the key file and is derived per purpose.
* **Recovery codes**: 10 chars from a 31-char alphabet (~50 bit), stored as HMAC-SHA256. Single use is enforced by
  one atomic `UPDATE ... WHERE used_at IS NULL`, so parallel requests cannot both succeed. Regeneration replaces all.
  The plain codes exist only in the response that shows them, never in the session or the database. If the page is
  reloaded before the user confirmed, they can create new codes in that step (they have just passed the second
  factor); the time limit of the pending login restarts when the step changes.
* **Replay protection**: the last accepted time step is stored per enrolment and advanced with a conditional
  `UPDATE`; a code of an already used (or older) step is rejected. Tolerance is one step (30 s) either way.
* **Brute force**: each account has a budget of 5 attempts per 15 minutes (TOTP and recovery codes share it).
  An attempt is counted *before* the code is checked, with one atomic `INSERT ... ON DUPLICATE KEY UPDATE`, so
  parallel requests cannot share an attempt: of 20 simultaneous guesses exactly 5 are evaluated, the rest are locked.
  A success clears the counter; denied attempts do not extend the lock. Not keyed by IP. The password step itself
  is unchanged core behaviour.
* **Recovery codes left**: the security page shows the number and warns at 2 or fewer.
* **Deleted accounts**: `User::delete()` is extended; `AccountCleanup` removes everything stored for that account.
* **Step-up**: disabling 2FA and regenerating recovery codes need a current code.
* **Existing enrolment wins**: with mode *optional* a user who has 2FA still gets challenged.
* **Trusted devices** are not implemented. If added later: random token, only its hash stored, expiry,
  revocable, listed per user.
* **Password reset** does not touch the 2FA tables; resetting 2FA is a separate, audited action.

## Known limits

* Switching the shop setting to *mandatory* cannot end API tokens that were issued before; they run out on their own
  (by default the access token after 8 hours and the refresh token after 24 hours). Enabling 2FA, making it mandatory for
  an account and resetting an account do end them.
* **Mandatory mode**: until an admin has set up 2FA, whoever knows the password can set up their own authenticator
  first. After switching it on, have every admin set up 2FA right away (reset: CLI or admin overview).
* **Locking**: someone who only knows the password can lock an account for 15 minutes by guessing. A main admin can
  release it in the admin overview.
* Sessions that were already logged in when mode *mandatory* is switched on stay valid until they end.
* Deactivating a user does not end running sessions (core behaviour); it does block finishing a pending login.

## Tests

* `tests/Unit` (no shop, no database): the behaviour of the use cases against in-memory ports; time is a mock clock,
  nothing sleeps. They are written as Given / When / Then and test outcomes, not internals.
* `tests/Database` (a real MariaDB/MySQL, no shop; `OXID2FA_TEST_DB_*`, the CI sets `OXID2FA_REQUIRE_DB=1`): the SQL,
  the real migration (up and down), and the guarantees that only a database can give: attempt counting, single use
  of a recovery code, spending a TOTP step, activation. The `ConcurrencyTest` fires 12 PHP processes at the same
  moment at the same row.
