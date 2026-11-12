# Contributing to LLM API Library

Thank you for considering contributing! Bug reports, ideas, documentation fixes and pull requests are all welcome.

## Reporting bugs

Before opening a new issue, please check if a similar one already exists. When reporting a bug, please include:

- The library version and PHP version.
- The provider and model involved, if any.
- What you expected and what actually happened.
- A minimal code example to reproduce it, **without API keys**.

## Suggesting features

Open an issue describing the problem you want to solve. A rough idea of the solution is welcome but not required.

## Pull requests

### Getting started

1. Fork the repository and clone your fork.
2. Install dependencies:
   ```bash
   composer install
   ```
3. Create a branch from `2.x` (or from `main` for a 1.x fix):
   ```bash
   git checkout -b my-feature 2.x
   ```

### Tests

All changes should be covered by tests. `tests/` mirrors the `src/` structure.

```bash
# Run the full test suite
composer run test

# Run a single test class
vendor/bin/phpunit tests/Chat/ChatTest.php
```

PHPUnit is strict about coverage metadata: each test class must declare every class it executes with
`#[CoversClass]` and `#[UsesClass]`. A missing `#[UsesClass]` passes without a coverage driver but fails on CI, so
run the suite with Xdebug or PCOV enabled:

```bash
vendor/bin/phpunit --coverage-text
```

### Static analysis

```bash
composer run analyse
```

### Coding standards

- `declare(strict_types=1);` in every PHP file.
- Explicit return types, `readonly` classes by default and `with*()` methods for immutable objects.
- `#[Override]` on interface method implementations.
- Alphabetically sorted `use` imports (see `.editorconfig`).
- Modules have dependency rules enforced by `tests/ArchitectureTest.php`: see
  [docs/architecture.md](docs/architecture.md).

### Changelog

For user-facing changes, add an entry to the `[Unreleased]` section of `CHANGELOG.md`, following
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

### Commit messages

We use [Conventional Commits](https://www.conventionalcommits.org/): `<type>(<optional scope>): <description>`, in
English and in the imperative mood.

```
feat(chat): add immutable conversations
fix(provider): handle empty Retry-After header
```

Mark breaking changes with `!` and explain the migration in a `BREAKING CHANGE:` footer.

### Submitting

1. Make sure tests and static analysis pass.
2. Push your branch to your fork.
3. Open a pull request against the `2.x` branch (or `main` for 1.x).
4. Be prepared to address feedback during review.

## Requirements

- PHP 8.3 or later
- Composer v2
- Optional extensions are listed in the `suggest` section of `composer.json` (e.g. `ext-curl` for progressive
  streaming, `ext-gd` or `ext-imagick` for image normalization)

## Code of conduct

Please be respectful and constructive. This project follows a [Code of Conduct](CODE_OF_CONDUCT.md).

For security issues, please follow the [Security Policy](SECURITY.md) instead of opening a public issue.
