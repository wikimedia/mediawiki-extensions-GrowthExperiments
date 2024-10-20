<?php

namespace GrowthExperiments\Mentorship\Provider;

use MediaWiki\Extension\CommunityConfiguration\Provider\IConfigurationProvider;
use MediaWiki\Extension\CommunityConfiguration\Store\WikiPageStore;
use MediaWiki\Status\StatusFormatter;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use StatusValue;
use Wikimedia\Rdbms\IDBAccessObject;

class CommunityStructuredMentorWriter extends AbstractStructuredMentorWriter {
	use CommunityGetMentorDataTrait;

	public function __construct(
		MentorProvider $mentorProvider,
		UserIdentityLookup $userIdentityLookup,
		UserFactory $userFactory,
		StatusFormatter $statusFormatter,
		IConfigurationProvider $provider
	) {
		parent::__construct( $mentorProvider, $userIdentityLookup, $userFactory );

		$this->statusFormatter = $statusFormatter;
		$this->provider = $provider;
	}

	protected function doSaveMentorData(
		array $mentorData,
		string $summary,
		UserIdentity $performer,
		bool $bypassWarnings
	): StatusValue {
		return $this->provider->alwaysStoreValidConfiguration(
			[ self::CONFIG_KEY => $mentorData ],
			$this->userFactory->newFromUserIdentity( $performer ),
			$summary
		);
	}

	/**
	 * @inheritDoc
	 */
	public function isBlocked(
		UserIdentity $performer, int $freshness = IDBAccessObject::READ_NORMAL
	): bool {
		$store = $this->provider->getStore();
		$block = $this->userFactory->newFromUserIdentity( $performer )->getBlock( $freshness );
		if ( $store instanceof WikiPageStore ) {
			return $block && $block->appliesToTitle( $store->getConfigurationTitle() );
		}
		return (bool)$block;
	}
}
