# GitHub Sync

A MediaWiki extension that triggers external GitHub sync scripts after page saves using the job queue.

## Requirements

- MediaWiki 1.37+
- PHP with cURL
- A running MediaWiki job queue
- Your own sync endpoints; scripts that commit and push to GitHub are not included

## Installation

Place this repository in `extensions/GitHubSync` and add the following to `LocalSettings.php`:

```php
wfLoadExtension( 'GitHubSync' );
$wgGitHubSyncEndpoints = [
    'https://example.org/wiki-sync.php',
];
```

Replace the example URL with your sync endpoint. No jobs are queued until endpoints are configured.

## Configuration

| Setting | Default | Description |
| --- | --- | --- |
| `$wgGitHubSyncEndpoints` | `[]` | Sync endpoint URLs |
| `$wgGitHubSyncConnectTimeout` | `3` | Connection timeout in seconds |
| `$wgGitHubSyncTimeout` | `10` | Request timeout in seconds |

Each endpoint receives a GET request with `page_title`, `user`, and `comment` parameters. All endpoints must return a 2xx status for the job to succeed. Redirects are not followed. Endpoints must handle repeated requests and provide their own access control.

See `examples/LocalSettings.example.php` for logging settings. Logs use the `GitHubSync` MediaWiki debug channel.

## Checks

```sh
php -n tests/smoke.php
```

GitHub Actions checks PHP syntax, the JSON manifest, and job behavior. Full MediaWiki integration requires a separate test installation.

Based on FreeCADGithubSync by Reqrefusion. No license was included in the original archive.
