<?php
// Standalone checks with MediaWiki and HTTP doubles; no network requests.
namespace {
	class Title {
		public function getPrefixedText() { return 'Test & Unicode'; }
	}
	class Job {
		public $params, $title, $removeDuplicates, $error;
		public function __construct( $type, $title, $params ) {
			check( $type === 'githubSync', 'Registered job name' );
			$this->title = $title;
			$this->params = $params;
		}
		public function setLastError( $error ) { $this->error = $error; }
	}
	function wfDebugLog( $channel, $message ) {}
	function check( $ok, $message ) {
		if ( !$ok ) { throw new \RuntimeException( $message ); }
	}
	if ( !function_exists( 'curl_init' ) ) {
		function curl_init() {}
	}
	foreach ( [ 'CURLOPT_RETURNTRANSFER', 'CURLOPT_WRITEFUNCTION', 'CURLOPT_FOLLOWLOCATION',
		'CURLOPT_CONNECTTIMEOUT', 'CURLOPT_TIMEOUT', 'CURLOPT_USERAGENT', 'CURLINFO_HTTP_CODE'
	] as $index => $constant ) {
		if ( !defined( $constant ) ) { define( $constant, $index + 1 ); }
	}
	class FakeServices {
		public $settings = [ 'GitHubSyncEndpoints' => [],
			'GitHubSyncConnectTimeout' => 3, 'GitHubSyncTimeout' => 10 ];
		public $queued = [];
		public function getMainConfig() { return $this; }
		public function get( $key ) { return $this->settings[$key]; }
		public function getJobQueueGroup() { return $this; }
		public function push( $job ) { $this->queued[] = $job; }
	}
}
namespace MediaWiki {
	class MediaWikiServices {
		public static $instance;
		public static function getInstance() { return self::$instance; }
	}
}
namespace MediaWiki\Extension\GitHubSync\Job {
	function curl_init( $url ) { $GLOBALS['requests'][] = $url; return new \stdClass(); }
	function curl_setopt_array( $ch, $options ) { $GLOBALS['options'] = $options; return true; }
	function curl_exec( $ch ) { return $GLOBALS['transport']; }
	function curl_getinfo( $ch, $key ) { return $GLOBALS['status']; }
	function curl_errno( $ch ) { return $GLOBALS['transport'] === false ? 7 : 0; }
	function curl_close( $ch ) {}
}
namespace {
	require __DIR__ . '/../includes/Hooks.php';
	require __DIR__ . '/../includes/Job/GithubSyncJob.php';
	$services = new FakeServices();
	\MediaWiki\MediaWikiServices::$instance = $services;
	$page = new class {
		public function getTitle() { return new Title(); }
	};
	$user = new class {
		public function getName() { return 'Editor'; }
	};
	$hook = [ \MediaWiki\Extension\GitHubSync\Hooks::class, 'onPageSaveComplete' ];
	$hook( $page, $user, 'Summary & edit', 0, null, null );
	check( count( $services->queued ) === 0, 'Disabled configuration queues nothing' );
	$services->settings['GitHubSyncEndpoints'] = [ 'https://example.org/sync?format=raw' ];
	$hook( $page, $user, 'Summary & edit', 0, null, null );
	check( count( $services->queued ) === 1, 'Save queues a job' );
	$job = $services->queued[0];
	$requests = [];
	$transport = true;
	$status = 204;
	check( $job->run() === true, '2xx succeeds' );
	parse_str( parse_url( $requests[0], PHP_URL_QUERY ), $query );
	check( $query === [ 'format' => 'raw', 'page_title' => 'Test & Unicode',
		'user' => 'Editor', 'comment' => 'Summary & edit' ], 'Metadata encoding and existing query' );
	check( $options[CURLOPT_FOLLOWLOCATION] === false, 'Redirects disabled' );
	$status = 500;
	check( $job->run() === false && $job->error !== null, 'HTTP error fails' );
	$status = 200;
	$transport = false;
	check( $job->run() === false, 'Transport error fails' );
	$transport = true;
	$services->settings['GitHubSyncEndpoints'] = [ 'file:///tmp/test', 'https://example.org/sync' ];
	$requests = [];
	check( $job->run() === false, 'Invalid URL fails the job' );
	check( count( $requests ) === 1, 'Other endpoints still attempted' );
	$services->settings['GitHubSyncEndpoints'] = [];
	check( $job->run() === true, 'Disabled jobs complete without HTTP' );
	echo "All behavior checks passed.\n";
}
