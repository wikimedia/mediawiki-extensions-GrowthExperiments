<?php

namespace GrowthExperiments\Mentorship\Provider;

use MediaWiki\Extension\CommunityConfiguration\Provider\IConfigurationProvider;
use MediaWiki\User\UserIdentityLookup;
use MessageLocalizer;

class CommunityStructuredMentorProvider extends AbstractStructuredMentorProvider {
	use CommunityGetMentorDataTrait;

	public function __construct(
		UserIdentityLookup $userIdentityLookup,
		MessageLocalizer $messageLocalizer,
		IConfigurationProvider $provider
	) {
		parent::__construct( $userIdentityLookup, $messageLocalizer );

		$this->provider = $provider;
	}
}
