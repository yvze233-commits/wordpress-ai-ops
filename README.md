# WordPress AI Ops

Standalone Laravel application for evidence-backed WordPress content operations.

The phase-one workflow selects four daily topics, reads approved knowledge and annotated image libraries, generates structured article drafts, runs a second AI review, and writes approved content to WordPress as drafts. Titles already processed are deduplicated. Writing and review Skills are versioned snapshots, and user-uploaded Skills are validated before activation.

## Local checks

```powershell
$env:DB_CONNECTION = 'sqlite'
$env:DB_DATABASE = ':memory:'
php artisan test
```

Set `AI_DEFAULT_MODEL`, WordPress connection credentials, and queue settings in `.env` for a real run. `PLAYWRIGHT_ENABLED` stays `false` unless the browser fallback has been tested against a disposable WordPress account.
