<?php

namespace GrowthExperiments\Config\Providers;

use MediaWiki\Extension\CommunityConfiguration\Provider\DataProvider;
use MediaWiki\Json\FormatJson;
use MediaWiki\Permissions\Authority;
use StatusValue;

class MentorListConfigProvider extends DataProvider {

	protected function finalizeLoadedStatus( StatusValue $status ): StatusValue {
		if ( $status->isOK() ) {
			$status->setResult(
				true,
				// needed to receive an associative array
				// REVIEW: Do we want to keep this? See T369608.
				FormatJson::decode( FormatJson::encode( $status->getValue() ), true )
			);
		}
		return $status;
	}

	/** @inheritDoc */
	public function storeValidConfiguration(
		$newConfig,
		Authority $authority,
		string $summary = '',
		?string $version = null
	): StatusValue {
		// needed, as CommunityConfiguration expects an object
		// REVIEW: Do we want to keep this? See T369608.
		$newConfig = FormatJson::decode( FormatJson::encode( $newConfig ), false );
		return parent::storeValidConfiguration( $newConfig, $authority, $summary, $version );
	}

	/** @inheritDoc */
	public function alwaysStoreValidConfiguration(
		$newConfig,
		Authority $authority,
		string $summary = '',
		?string $version = null,
	): StatusValue {
		// needed, as CommunityConfiguration expects an object
		// REVIEW: Do we want to keep this? See T369608.
		$newConfig = FormatJson::decode( FormatJson::encode( $newConfig ), false );
		return parent::alwaysStoreValidConfiguration( $newConfig, $authority, $summary, $version );
	}
}
