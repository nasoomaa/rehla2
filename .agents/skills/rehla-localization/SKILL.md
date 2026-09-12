---
name: rehla-localization
description: Use when creating any Rehla package or changing customer/staff text, translated content, locale behavior, accessibility labels, or RTL presentation.
---

# Rehla Localization

## When to use

Use for every package creation task and every change that can show text to a customer orstaff, publish bilingual content, map an error, oraffect Arabic layout.

## Authorities

Read the selected plan task, `../../../specs/cross-cutting/localization-accessibility-and-errors.md`, package README, and its language files.

## Rules

- Every package has `src/resources/lang/en/messages.php`, `src/resources/lang/ar/messages.php`, and `tests/Architecture/TranslationCompletenessTest.php` from creation.
- The provider loads `src/resources/lang` with namespace `rehla-<lowercase-package>`.
- EN and AR key sets and scalar/array shapes match recursively. Empty paired arrays are valid; a missing file is not.
- Add both languages in the same commit as public text. Do not hardcode visible copy in Actions, Controllers, Jobs, Policies, Livewire, orFilament definitions.
- API `code`, identifiers, and schema keys remain language-neutral. Only human title/detail are localized.
- Service, form, content, and visible settings cannot publish with a required language missing.
- Browser proof covers `lang`, `dir=rtl`, CSS logical properties, keyboard, focus, labels, errors, overflow, and serious/critical accessibility findings.

## Workflow

Identify every visible state and error, choose stable domain-oriented keys, add paired values, render through namespace keys, update publication validation, then test parity, missing keys, fallback, Arabic/RTL, and accessibility.

## Verification

Run package translation tests, the repository visible-text/provider guard, affected feature tests in EN and AR, and browser RTL/accessibility scenarios. Re-run after formatter orview build.

## Stop conditions

Stop publication orcompletion when a translation is unknown, the Arabic meaning changes the contract, keys diverge, a literal bypasses the guard, orRTL makes an action unusable.

## Handoff evidence

List keys and namespaces changed, parity/provider results, EN/AR screenshots orbrowser artifacts where relevant, RTL/accessibility results, and any intentionally technical allowlist entry.
