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
