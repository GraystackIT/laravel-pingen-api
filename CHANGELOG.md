# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [Unreleased]

### Added
- First implementation: OAuth2 `client_credentials` authentication with a cached access token,
  the three-call letter flow (request an upload slot, PUT the PDF, create the letter), letter
  management (list, read, edit, submit, cancel, delete, download, price calculation), letter
  events, webhook management, the inbound webhook endpoint with signature verification and a
  replay guard, and optional database storage of letters and webhook deliveries.

- `pingen:webhooks` lists the registered webhooks, or points them all at an endpoint. The listing
  says per row whether the key a webhook was registered with still matches the configured one,
  comparing it rather than printing it. Both failures it makes visible are otherwise silent: a
  webhook aimed at a host that no longer answers, and one registered under a key the application
  no longer holds, produce no deliveries, no errors and nothing in any log.
- The inbound endpoint logs a delivery it verified and claimed but could do nothing with. It still
  answers 200, which from the provider side is indistinguishable from a delivery that was handled.

### Fixed
- The README registered a webhook for an `eventCategory` of `letters`, which the API refuses with a
  422. The valid categories are now named.

### Changed
- Requires PHP 8.3 or newer (was 8.2). Laravel 13 joins the CI matrix.
- README restructured to match the other GraystackIT API packages.
