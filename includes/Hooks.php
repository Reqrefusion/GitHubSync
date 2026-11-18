<?php

namespace MediaWiki\Extension\GitHubSync;

use MediaWiki\MediaWikiServices;

/**
 * Hooks for the GitHubSync extension.
 *
 * This hook only queues a background job and returns quickly,
 * so it should not noticeably slow down page saves.
 */
class Hooks {

	/**
	 * PageSaveComplete hook handler.
	 *
	 * @param \WikiPage $wikiPage
	 * @param \MediaWiki\User\UserIdentity $user
	 * @param string $summary
	 * @param int $flags
	 * @param \MediaWiki\Revision\RevisionRecord $revisionRecord
	 * @param \MediaWiki\Storage\EditResult $editResult
	 * @return bool
	 */
	public static function onPageSaveComplete(
		$wikiPage,
		$user,
		$summary,
		$flags,
		$revisionRecord,
		$editResult
	) {
		$config = MediaWikiServices::getInstance()->getMainConfig();
		if ( !$config->get( 'GitHubSyncEndpoints' ) ) {
			return true;
		}

		$title = $wikiPage->getTitle();
		$titleText = $title->getPrefixedText();
		$userName = $user->getName();

		self::log(
			sprintf(
				'%s [HOOK] PageSaveComplete for "%s" by "%s"',
				self::now(),
				$titleText,
				$userName
			)
		);

		$params = [
			'page_title' => $titleText,
			'user'       => $userName,
			'summary'    => $summary,
		];

		// Create job instance
		$job = new Job\GithubSyncJob( $title, $params );

		// Push job to the queue using the recommended service
		$jobQueueGroup = MediaWikiServices::getInstance()->getJobQueueGroup();
		$jobQueueGroup->push( $job );

		self::log(
			sprintf(
				'%s [HOOK] Queued GithubSyncJob with params: %s',
				self::now(),
				json_encode( $params )
			)
		);

		// Always allow other handlers to run
		return true;
	}

	/**
	 * Write to the MediaWiki GitHubSync debug channel.
	 *
	 * @param string $message
	 * @return void
	 */
	public static function log( string $message ): void {
		wfDebugLog( 'GitHubSync', $message );
	}

	/**
	 * Get current time in ISO-8601 format.
	 *
	 * @return string
	 */
	private static function now(): string {
		return gmdate( 'c' );
	}
}
