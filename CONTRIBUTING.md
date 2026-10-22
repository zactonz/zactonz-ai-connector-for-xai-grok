# Contributing

Thanks for helping improve Zactonz AI Connector for xAI Grok.

## Local Setup

Install development dependencies:

```bash
composer install
```

Run the quality suite before opening a pull request:

```bash
composer lint
```

You can also run checks individually:

```bash
composer test
composer phpcs
composer phpstan
```

## Pull Request Guidelines

- Keep changes focused on one issue or feature.
- Follow the existing WordPress coding style.
- Add or update tests when changing model discovery, settings sanitization, diagnostics, or request construction.
- Update `readme.txt`, `README.md`, and `CHANGELOG.md` when user-facing behavior changes.
- Do not commit `vendor/`, generated ZIP files, or local test caches.

## WordPress.org Releases

Release packages are built from the plugin source while excluding the development files listed in `.distignore`.
