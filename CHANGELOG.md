# Changelog

All notable changes to this extension are documented here. The format
is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/).

## [1.1.8] - 2026-10-30

### Changed
- Released under the MIT License: this module is free and open-source software.

## [1.1.7] - 2026-10-05

### Fixed
- Emptying the cart now removes its stored fee lines, so the cart and checkout totals of an empty cart no longer list the old fees.
- Saving a fee rule without changes keeps empty optional fields (minimum and maximum fee, subtotal and quantity limits, dates) empty instead of turning them into 0.
- Fee rules reject negative amounts, percentages, subtotals and quantities, a minimum above its maximum, and a From Date later than the To Date, in the form and on save. A rule fee can no longer be negative, so it can never lower the order total.
- Invoice and credit memo PDFs now print the fee lines, so the printed totals add up to the grand total.
- The Small Order Fee settings are hidden while the fee is disabled, and its Fee Type offers only the two types it supports (Fixed Amount, Percentage of Subtotal).
- The rule form explains which fee types use Fee Amount and Fee Amount Percent, the unused Include in Subtotal option was removed from the form, and the Fee Rules grid shows a Fee Percent column.
