# Changelog

All notable changes to this extension are documented here. The format
is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/).

## [1.1.6] - 2026-10-03

### Fixed
- Fee rules limited to the NOT LOGGED IN customer group (id 0) now apply only to guests. The `0` id was dropped when the rule lists were split, so such a rule applied to every customer group; store id `0` (All Store Views) is now kept as well.
- The `panth:extrafee:install-sample-data` percent rules (Payment Processing Fee, Order Insurance Fee) store their rate in `fee_amount_percent`, so they charge the configured percentage instead of 0.
- The cart small-order message no longer throws a TypeError when the quote has no currency code yet.
- The admin Delete button on the fee rule edit page escapes the translated confirmation text for JavaScript, so a translation containing an apostrophe or quote no longer breaks the click handler.
