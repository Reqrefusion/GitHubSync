<?php

namespace MediaWiki\Extension\GitHubSync\Job;

use Job;
use Title;
use MediaWiki\MediaWikiServices;
use MediaWiki\Extension\GitHubSync\Hooks;

/** Notify external sync scripts asynchronously after a page save. */
class GithubSyncJob extends Job {
	public function __construct( Title $title, array $params ) {
		parent::__construct( 'githubSync', $title, $params );
		$this->removeDuplicates = true;
	}

	public function run() {
		$config = MediaWikiServices::getInstance()->getMainConfig();
		$endpoints = $config->get( 'GitHubSyncEndpoints' );
		if ( !$endpoints ) {
			return true;
		}
		if ( !function_exists( 'curl_init' ) ) {
			return $this->fail( 'The PHP cURL extension is required.' );
		}
		$query = http_build_query( [
			'page_title' => $this->params['page_title'] ?? $this->title->getPrefixedText(),
			'user' => $this->params['user'] ?? '',
			'comment' => $this->params['summary'] ?? '',
		], '', '&', PHP_QUERY_RFC3986 );
		$success = true;
		foreach ( $endpoints as $index => $endpoint ) {
			$parts = is_string( $endpoint ) ? parse_url( $endpoint ) : false;
			if ( !$parts || empty( $parts['host'] ) ||
				!in_array( strtolower( $parts['scheme'] ?? '' ), [ 'http', 'https' ], true ) ||
				isset( $parts['fragment'] ) || isset( $parts['user'] ) || isset( $parts['pass'] )
			) {
				$this->fail( "Endpoint $index must be an HTTP(S) URL without credentials or a fragment." );
				$success = false;
				continue;
			}
			$separator = strpos( $endpoint, '?' ) === false ? '?' : '&';
			$ch = curl_init( $endpoint . $separator . $query );
			if ( $ch === false ) {
				$this->fail( "Unable to initialize endpoint $index." );
				$success = false;
				continue;
			}
			curl_setopt_array( $ch, [
				CURLOPT_RETURNTRANSFER => false,
				CURLOPT_WRITEFUNCTION => static function ( $handle, $data ) {
					return strlen( $data );
				},
				CURLOPT_FOLLOWLOCATION => false,
				CURLOPT_CONNECTTIMEOUT => max( 1, (int)$config->get( 'GitHubSyncConnectTimeout' ) ),
				CURLOPT_TIMEOUT => max( 1, (int)$config->get( 'GitHubSyncTimeout' ) ),
				CURLOPT_USERAGENT => 'MediaWiki-GitHubSync/1.1',
			] );
			$result = curl_exec( $ch );
			$status = (int)curl_getinfo( $ch, CURLINFO_HTTP_CODE );
			$error = curl_errno( $ch );
			curl_close( $ch );
			if ( $result === false || $status < 200 || $status >= 300 ) {
				$this->fail( "Endpoint $index failed (HTTP $status, cURL $error)." );
				$success = false;
			} else {
				Hooks::log( "Endpoint $index completed (HTTP $status)." );
			}
		}
		return $success;
	}

	private function fail( string $message ): bool {
		$this->setLastError( $message );
		Hooks::log( $message );
		return false;
	}
}
