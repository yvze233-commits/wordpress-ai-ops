# WordPress Browser Operations Runbook

Phase one writes drafts only. REST is the default adapter; the Playwright fallback is disabled unless `PLAYWRIGHT_ENABLED=true`.

## REST setup

1. Create a WordPress Application Password for a dedicated operator account.
2. Store the site URL, username, and password in the encrypted WordPress connection form. The password is never written to logs or audit payloads.
3. Run the connection health check and a draft-only test. A connection using HTTP is accepted only in local/testing environments; production connections must use HTTPS.

## Browser smoke test

Install Playwright in the project workspace, then run the opt-in test against a disposable WordPress account:

```powershell
$env:WP_TEST_URL = 'https://test.example.com'
$env:WP_TEST_USERNAME = 'operator'
$env:WP_TEST_PASSWORD = 'application-password'
npx playwright test playwright/wordpress-draft-publisher.spec.ts
```

The smoke test uses labels and roles, saves a draft, captures the post ID from the editor URL, and trashes only that test draft. Do not point it at a production account.

## Recovery

- `401` and `403` stop immediately and require credential or permission changes.
- `429` and `5xx` are retryable with backoff.
- A request timeout is reconciled by the idempotency marker before another create request.
- If the editor changes its labels, update the Playwright selectors and keep the REST adapter as the primary path.
