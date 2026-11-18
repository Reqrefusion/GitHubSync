<?php

// Copy into the wiki's LocalSettings.php and set your own endpoints.
wfLoadExtension( 'GitHubSync' );
$wgGitHubSyncEndpoints = [
	'https://example.org/wiki-sync.php',
	'https://example.org/wiki-html-sync.php',
];
$wgGitHubSyncConnectTimeout = 3;
$wgGitHubSyncTimeout = 10;
// Optional: absolute path writable by PHP, outside the public web root.
// $wgDebugLogGroups['GitHubSync'] = '/var/log/mediawiki/github-sync.log';
