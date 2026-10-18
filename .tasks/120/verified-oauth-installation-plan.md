# План реализации issue #120: проверка OAuth-авторизации при установке

## Summary

Move reusable verification of incoming Bitrix24 OAuth authorization into the library and require it before installation writes. Keep consumer application sessions, TTL, consent rules, exports and custom-domain allowlists outside this change. Issue: https://github.com/mesilov/bitrix24-php-lib/issues/120. Base: origin/dev (`0edc8fc`).

## Target contract

- Add `src/ApplicationInstallations/Services/Authorization/AuthorizationVerifierInterface.php` with `verify(AuthToken $authToken, Domain $domain, string $memberId, ?int $expectedUserId = null): VerifiedAuthorization`.
- Add immutable `VerifiedAuthorization.php`: confirmed client ID, member ID, domain, user ID, effective AuthToken/expiry and verified user profile.
- Add `SdkAuthorizationVerifier.php`, configured with trusted application profile and OAuth server configuration. Use SDK CoreBuilder/credentials and the SDK secure `app.info` calling convention, not a direct Symfony HTTP implementation copied from the consumer.
- The SDK secure check returns vendor metadata: validate client_id, install.member_id, install.uri, positive user_id and valid expiry. The portal app.info CODE is not the OAuth client_id.
- Do not require installed=true during installation. Only contact the portal for profile after vendor identity matches; compare profile.ID with vendor user_id and expected user when supplied.
- Preserve SDK token renewal semantics and return the effective verified credentials. Do not emit installation/account domain events or perform persistence before verification succeeds. SDK transport must not send raw credentials to application logs.
- Verification failures distinguish invalid/mismatched authorization from upstream unavailability without including tokens, request/response objects or secrets in exception messages.

## Production changes

1. Add the reusable interface, verified result, SDK implementation and a controlled `AuthorizationVerificationException` in the authorization service folder.
2. Add a required verifier dependency to `src/ApplicationInstallations/UseCase/Install/Handler.php`. Verify before querying/deactivating an old installation or saving a new pair. Store the verified effective AuthToken. Match command user identity/admin information against the verified profile; reject inconsistent input.
3. Add an AuthToken argument to `src/ApplicationInstallations/UseCase/OnAppInstall/Command.php` and a required verifier dependency to its Handler. Require verified event credentials before finishing a pending installation; bind verified identity to the pending master account. Preserve the verified duplicate-event no-op behavior. No nullable verifier, no tokenless fallback.
4. Remove secret-bearing log fields in the modified installation handlers; use only safe outcome/error metadata for authorization diagnostics.
5. Update `src/ApplicationInstallations/Docs/application-installations.md` and README integration examples with dependency wiring, passing event authToken, reuse before issuing consumer sessions, upstream failure handling and migration notes. Add a release-facing BC entry to CHANGELOG in its existing top-section style; do not introduce an Unreleased section or invent a release date.

## Tests

- New unit tests under `tests/Unit/ApplicationInstallations/Services/Authorization/`: SDK transport fake proves secure app.info targets the configured OAuth server, no portal call before vendor validation, then profile uses the verified domain.
- Valid pending installation; foreign client, member, domain, user; expired/numeric/ISO8601 expiry; malformed/missing metadata; upstream/transport failure; no raw secrets in logs/exceptions; sequential verifications do not reuse another request credentials.
- Update `tests/Unit/ApplicationInstallations/UseCase/Install/HandlerTest.php`: failed verification makes zero repository save/flush calls and does not deactivate an existing installation; valid UI/one-step/reinstall flows still pass and use verified credentials.
- Update `tests/Unit/ApplicationInstallations/UseCase/OnAppInstall/{CommandTest,HandlerTest}.php`: OAuth credentials required; failed verification leaves pending state unchanged; valid completion and verified duplicates preserved.
- Extend functional Install and OnAppInstall tests with a recording/failing verifier: real database state stays unchanged on rejection. No live customer portals or outbound API calls in automated suites.
- Update all test helpers and constructor call sites; grep for both handlers and commands to cover direct consumers.

## Environment and verification

The checkout has a stale ignored composer.lock/vendor (SDK dev-dev), while composer.json requires dev-v3-dev. Resolve the local environment through Makefile Composer targets before relying on vendor APIs; do not commit ignored lock/vendor or change package constraints without a verified need.

Run tests/linters only via Makefile. Write a focused regression first and observe RED using a make entrypoint; implement, then run the repository quality gate in its required order: `make lint-all`, `make test-unit`, `make test-functional`. Functional tests recreate only this library test database; confirm its target and keep unrelated data untouched. Do not expose debug-show-env password output in reports.

## GitHub workflow

Issue #120 is created and read back after duplicate/label checks. Branch: `feature/120-verified-oauth-installation`, based on origin/dev. Plan: `.tasks/120/verified-oauth-installation-plan.md`. Preserve the pre-existing .mcp.json and opencode.json changes. The maintainer skill requires explicit plan review before production edits. After approved implementation and green gates, re-read `.github/PULL_REQUEST_TEMPLATE.md`, create a PR against dev with `Fix #120` and honest verification. Do not merge or release the library automatically.

## External context

Official Bitrix24 MCP is available under b24_dev_mcp and was live-checked. Fetched app.info method documentation: https://apidocs.bitrix24.ru/api-reference/common/system/app-info.html. It documents the regular portal call, not the vendor metadata response. The secure calling convention is established by installed current SDK code in the consumer (Main::guardValidateCurrentAuthToken and EndpointUrlFormatter); verify it against the resolved SDK version during environment setup.

## Approval and current state

- Issue #120 created after duplicate and label checks; GitHub CLI browser authorization completed.
- The proposed ownership boundary was agreed in conversation. The repository-local maintainer skill additionally requires explicit review of this concrete plan before production code changes.
- Current deliverable is a plan-only draft PR. No production changes, Composer resolution, tests or linters have run yet. After plan approval, implement in this same branch, complete the required gates and update the PR.
