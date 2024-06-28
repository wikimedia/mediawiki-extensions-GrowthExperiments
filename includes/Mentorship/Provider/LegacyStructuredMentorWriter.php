<?php

namespace GrowthExperiments\Mentorship\Provider;

use GrowthExperiments\Config\WikiPageConfigLoader;
use GrowthExperiments\Config\WikiPageConfigWriterFactory;
use IDBAccessObject;
use MediaWiki\Title\Title;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use StatusValue;

class LegacyStructuredMentorWriter extends AbstractStructuredMentorWriter {
	use LegacyGetMentorDataTrait;

	private WikiPageConfigWriterFactory $configWriterFactory;
	private Title $mentorList;

	/**
	 * @param MentorProvider $mentorProvider
	 * @param UserIdentityLookup $userIdentityLookup
	 * @param UserFactory $userFactory
	 * @param WikiPageConfigLoader $configLoader
	 * @param WikiPageConfigWriterFactory $configWriterFactory
	 * @param Title $mentorList
	 */
	public function __construct(
		MentorProvider $mentorProvider,
		UserIdentityLookup $userIdentityLookup,
		UserFactory $userFactory,
		WikiPageConfigLoader $configLoader,
		WikiPageConfigWriterFactory $configWriterFactory,
		Title $mentorList
	) {
		parent::__construct( $mentorProvider, $userIdentityLookup, $userFactory );

		$this->configLoader = $configLoader;
		$this->configWriterFactory = $configWriterFactory;
		$this->mentorList = $mentorList;
	}

	/**
	 * Wrapper around WikiPageConfigWriter to save all mentor data
	 *
	 * @param array $mentorData
	 * @param string $summary
	 * @param UserIdentity $performer
	 * @param bool $bypassWarnings Should warnings raised by the validator stop the operation?
	 * @return StatusValue
	 */
	protected function doSaveMentorData(
		array $mentorData,
		string $summary,
		UserIdentity $performer,
		bool $bypassWarnings
	): StatusValue {
		$configWriter = $this->configWriterFactory
			->newWikiPageConfigWriter( $this->mentorList, $performer );
		$configWriter->setVariable( self::CONFIG_KEY, $mentorData );
		return $configWriter->save( $summary, false, self::CHANGE_TAG, $bypassWarnings );
	}

	/**
	 * @inheritDoc
	 */
	public function isBlocked(
		UserIdentity $performer,
		int $freshness = IDBAccessObject::READ_NORMAL
	): bool {
		$block = $this->userFactory->newFromUserIdentity( $performer )->getBlock( $freshness );
		return $block && $block->appliesToTitle( $this->mentorList );
	}
}
