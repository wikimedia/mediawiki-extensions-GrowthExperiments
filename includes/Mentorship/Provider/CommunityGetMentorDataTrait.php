<?php

namespace GrowthExperiments\Mentorship\Provider;

use MediaWiki\Extension\CommunityConfiguration\Provider\IConfigurationProvider;
use MediaWiki\Status\StatusFormatter;
use Psr\Log\LoggerInterface;
use RuntimeException;

trait CommunityGetMentorDataTrait {

	private IConfigurationProvider $provider;
	private StatusFormatter $statusFormatter;

	// NOTE: This needs to be protected, because MentorProvider declares this as protected
	protected LoggerInterface $logger;

	protected function getMentorData(): array {
		$result = $this->provider->loadValidConfiguration();
		if ( !$result->isOK() ) {
			// Loading the mentor list failed. Log an error and return an empty array.
			$this->logger->error( ...$this->statusFormatter->getPsr3MessageAndContext( $result, [
				'impact' => 'No data about mentors can be found; wiki behaves as if it had no mentors at all',
				'exception' => new RuntimeException,
			] ) );
			return [];
		}

		$data = $result->getValue();
		if ( !is_array( $data ) ) {
			$this->logger->error(
				__METHOD__ . ' failed to convert mentor list to an associative array',
				[
					'impact' => 'No data about mentors can be found; wiki behaves as if it had no mentors at all',
					'exception' => new RuntimeException,
				]
			);
			return [];
		}
		if ( !array_key_exists( CommunityStructuredMentorWriter::CONFIG_KEY, $data ) ) {
			// TODO: Remove this when the mentor list gets a JSON schema (CONFIG_KEY will be then
			// populated via the defaults system).
			return [];
		}
		return $data[CommunityStructuredMentorWriter::CONFIG_KEY];
	}
}
