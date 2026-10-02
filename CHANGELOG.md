# Change Log

All notable changes to this project will be documented in this file.
The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [1.1.1] - 2026-10-02

### Security
- GraphQL tokens of an account are ended when the account gets a second factor, when 2FA is made mandatory for it
  and when it is reset. Before, a refresh token from the time without 2FA could still renew access without a code.

### Changed
- The two admin pages "2FA – Mein Konto" and "2FA – Administratoren" are one page, *Service → 2FA*, with the sections
  *Mein Konto*, *Administratoren* (main admins) and *Protokoll* (main admins), built from OXID's group and list tables

### Fixed
- The audit log showed a missing translation for refused sign-ins (`2FA_LOGIN_REFUSED`)

## [1.1.0] - 2026-10-01

### Added
- GraphQL: the query `twoFactorLogin(username, password, code)` for administrators with 2FA (needs the GraphQL base
  module, which stays optional)

### Security
- Administrators who owe a second factor can no longer sign in with their password alone outside the admin area
  (shop front end, GraphQL API, other modules). They are refused and `2FA_LOGIN_REFUSED` is logged. Before, a GraphQL
  token could be requested with the password only.

## [1.0.0] - 2026-10-01

### Added
- TOTP two-factor authentication for shop administrators: setup, recovery codes, optional or mandatory mode,
  reset, audit log. See `docs/architecture.md`.
- Setting for a missing encryption key: block admins with 2FA (default) or allow password-only login.
  The module creates its key on activation, and the settings page shows a key check.
- Main administrators see all admins with their 2FA status, can require 2FA per account, reset others and
  release locks. The audit log is shown in the admin.
- The security page shows how many recovery codes are left and warns at 2 or fewer.
- A deleted user takes its 2FA data with it.
