<?php

namespace GrowthExperiments\Mentorship\Provider;

use MediaWiki\Extension\CommunityConfiguration\Provider\IConfigurationProvider;
use MediaWiki\Status\StatusFormatter;
use MediaWiki\User\UserIdentityLookup;
use MessageLocalizer;

class CommunityStructuredMentorProvider extends AbstractStructuredMentorProvider {
	use CommunityGetMentorDataTrait;

	public function __construct(
		UserIdentityLookup $userIdentityLookup,
		MessageLocalizer $messageLocalizer,
		IConfigurationProvider $provider,
		StatusFormatter $statusFormatter
	) {
		parent::__construct( $userIdentityLookup, $messageLocalizer );

		$this->provider = $provider;
		$this->statusFormatter = $statusFormatter;
	}
}
