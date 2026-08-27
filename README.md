# WordPress Content Analysis

Fetches content from a WordPress site 'https://pve.wxv.mybluehost.me/' via the authenticated REST API (including drafts and private posts), runs analysis on it, and produces a structured report.

The pipeline runs in four stages: **authenticated access → data fetching → analysis → report**. See [PLANNING.md](PLANNING.md) for details.

## Audit Rules

Each fetched item is checked against five rules; the report lists how many items were checked and how many issues were found per rule.

| Rule | Name                   | Flags an item when…                                                      |
| ---- | ---------------------- | ------------------------------------------------------------------------ |
| R1   | Missing title          | The rendered title is empty after trimming                               |
| R2   | Short content          | The visible text content contains fewer than 150 words                   |
| R3   | Missing featured image | A post has no featured image (pages are not flagged for this rule)       |
| R4   | Missing excerpt        | A published post has no meaningful excerpt                               |
| R5   | Stale draft            | An item is still a draft and has not been modified for more than 30 days |

## Requirements

- PHP >= 8.2 with the `curl` and `json` extensions
- [Composer](https://getcomposer.org/)

## Setup

1. Make sure [Composer](https://getcomposer.org/download/) is installed, then generate the autoloader:

   ```
   composer install
   ```

2. Create your config from the example and fill in your credentials:

   ```
   cp .env.example .env
   ```

   | Variable          | Description                                      |
   | ----------------- | ------------------------------------------------ |
   | `WP_BASE_URL`     | WordPress site base URL (no trailing slash)      |
   | `WP_USER`         | WordPress username                               |
   | `WP_APP_PASSWORD` | Application password for REST API authentication |

   > Generate an application password in WordPress under **Users → Profile → Application Passwords**.

## Usage

```
php index.php
```

The report is written to the `output/` directory.

## Tests

```
composer require --dev phpunit/phpunit   # first time only
./vendor/bin/phpunit tests
```
