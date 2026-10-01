# Change Log

All notable changes to this project will be documented in this file.
The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

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
