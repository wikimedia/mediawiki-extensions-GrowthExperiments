<?php

declare( strict_types = 1 );

namespace GrowthExperiments\Campaigns;

use GrowthExperiments\VariantHooks;
use MediaWiki\Context\IContextSource;
use MediaWiki\User\Options\UserOptionsLookup;

class CampaignLoader {
	public function __construct(
		private readonly IContextSource $context,
		private readonly UserOptionsLookup $userOptionsLookup,
	) {
	}

	/**
	 * Get the campaign from the user's saved options, falling back to the request parameter if
	 * the user's option isn't set. This is needed because the query parameter can get lost
	 * during CentralAuth redirection.
	 */
	public function getCampaign(): string {
		$campaignFromRequestQueryParameter = $this->context->getRequest()->getVal( 'campaign', '' );
		if ( defined( 'MW_NO_SESSION' ) ) {
			// If we're in a ResourceLoader context, don't attempt to get the campaign string
			// from the user's preferences, as it's not allowed.
			return $campaignFromRequestQueryParameter;
		}

		$user = $this->context->getUser();
		if ( !$user->isSafeToLoad() ) {
			return $campaignFromRequestQueryParameter;
		}
		// URL parameter takes precedence if present
		if ( $campaignFromRequestQueryParameter !== '' ) {
			return $campaignFromRequestQueryParameter;
		}
		// fallback to user preference if no URL parameter exists
		return $this->userOptionsLookup->getOption(
			$user,
			VariantHooks::GROWTH_CAMPAIGN,
			$campaignFromRequestQueryParameter,
		);
	}

}
