# Change Log

All notable changes to this project will be documented in this file.
The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]

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
