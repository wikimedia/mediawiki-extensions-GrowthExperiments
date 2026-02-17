<?php

namespace GrowthExperiments\Config\Providers;

use MediaWiki\Extension\CommunityConfiguration\Provider\DataProvider;
use MediaWiki\Json\FormatJson;
use MediaWiki\Permissions\Authority;
use StatusValue;

class MentorListConfigProvider extends DataProvider {

	private function manipulateStatus( StatusValue $status ): StatusValue {
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

	public function loadValidConfiguration(): StatusValue {
		return $this->manipulateStatus( parent::loadValidConfiguration() );
	}

	public function loadValidConfigurationUncached(): StatusValue {
		return $this->manipulateStatus( parent::loadValidConfigurationUncached() );
	}

	/** @inheritDoc */
	public function storeValidConfiguration(
		$newConfig,
		Authority $authority,
		string $summary = ''
	): StatusValue {
		// needed, as CommunityConfiguration expects an object
		// REVIEW: Do we want to keep this? See T369608.
		$newConfig = FormatJson::decode( FormatJson::encode( $newConfig ), false );
		return parent::storeValidConfiguration( $newConfig, $authority, $summary );
	}

	/** @inheritDoc */
	public function alwaysStoreValidConfiguration(
		$newConfig,
		Authority $authority,
		string $summary = ''
	): StatusValue {
		// needed, as CommunityConfiguration expects an object
		// REVIEW: Do we want to keep this? See T369608.
		$newConfig = FormatJson::decode( FormatJson::encode( $newConfig ), false );
		return parent::alwaysStoreValidConfiguration( $newConfig, $authority, $summary );
	}
}
