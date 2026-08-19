# Development

Tooling and conventions for contributors.

## Requirements

- PHP ^8.3
- Composer 2
- Optional: `amphp/websocket-client` for WebSocket tests / examples

## Setup

### 01

#### Install dependencies

Terminal

```bash
composer install
```

### 02

#### Run the test suite

Terminal

```bash
composer test
```

### 03

#### Run static analysis

Terminal

```bash
composer stan
```

### 04

#### Check code style

The project follows Tradernet / FFTech PHP style (PSR-12 + PER clarifications) via PHP CS Fixer.

Terminal

```bash
composer cs
```

To apply fixes:

```bash
vendor/bin/php-cs-fixer fix
```

## Layout

```text
src/           SDK library (Tradernet\Sdk\)
tests/         PHPUnit unit tests
examples/      Runnable samples (load .env via bootstrap)
docs/          English documentation
```

## Language

All public documentation, comments, exception messages, and examples are written in **English**.
