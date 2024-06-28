<?php

namespace GrowthExperiments\Mentorship\Provider;

use GrowthExperiments\Config\WikiPageConfigLoader;
use MediaWiki\Title\Title;
use MediaWiki\User\UserIdentityLookup;
use MessageLocalizer;

class LegacyStructuredMentorProvider extends AbstractStructuredMentorProvider {
	use LegacyGetMentorDataTrait;

	public function __construct(
		UserIdentityLookup $userIdentityLookup,
		MessageLocalizer $messageLocalizer,
		WikiPageConfigLoader $configLoader,
		Title $mentorList
	) {
		parent::__construct( $userIdentityLookup, $messageLocalizer );

		$this->configLoader = $configLoader;
		$this->mentorList = $mentorList;
	}
}
