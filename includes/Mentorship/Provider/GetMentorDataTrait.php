<?php

namespace GrowthExperiments\Mentorship\Provider;

use GrowthExperiments\Config\WikiPageConfigLoader;
use MediaWiki\Linker\LinkTarget;
use Psr\Log\LoggerAwareTrait;
use Status;
use StatusValue;

/**
 * Helper to share code between StructuredMentorWriter and StructuredMentorProvider
 *
 * This declares WikiPageConfigLoader $configLoader and LinkTarget $mentorList as private
 * variables, which need to be set by the constructor.
 */
trait GetMentorDataTrait {
	use LoggerAwareTrait;

	private WikiPageConfigLoader $configLoader;
	private LinkTarget $mentorList;

	/**
	 * Wrapper around WikiPageConfigLoader
	 *
	 * Guaranteed to return a valid mentor list. If a valid mentor list cannot be constructed
	 * using the wiki page, it constructs an empty mentor list instead and logs an error.
	 *
	 * This is cached within WikiPageConfigLoader.
	 *
	 * @return array
	 */
	private function getMentorData(): array {
		$res = $this->configLoader->load( $this->mentorList );
		if ( $res instanceof StatusValue ) {
			// Loading the mentor list failed. Log an error and return an empty array.
			$this->logger->error(
				__METHOD__ . ' failed to load mentor list: {error}',
				[
					'error' => Status::wrap( $res )->getWikiText( false, false, 'en' ),
					'impact' => 'No data about mentors can be found; wiki behaves as if it had no mentors at all'
				]
			);
			return [];
		}
		return $res['Mentors'];
	}

}
