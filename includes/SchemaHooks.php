<?php

namespace GrowthExperiments;

use Exception;
use MediaWiki\Hook\UnitTestsAfterDatabaseSetupHook;
use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;

/**
 * LoadExtensionSchemaUpdates hook handler.
 * This hook handler must not have any service dependencies.
 */
class SchemaHooks implements LoadExtensionSchemaUpdatesHook, UnitTestsAfterDatabaseSetupHook {

	/** @inheritDoc */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		global $wgGEDatabaseCluster;
		if ( $wgGEDatabaseCluster ) {
			throw new Exception( 'Cannot use automatic schema upgrades when not on the '
				. 'default cluster' );
		}

		$sqlDir = __DIR__ . '/../sql';
		$engine = $updater->getDB()->getType();
		$updater->addExtensionTable( 'growthexperiments_link_recommendations',
			"$sqlDir/$engine/growthexperiments_link_recommendations.sql" );
		$updater->addExtensionTable( 'growthexperiments_link_submissions',
			"$sqlDir/$engine/growthexperiments_link_submissions.sql" );
		$updater->addExtensionTable( 'growthexperiments_mentee_data',
			"$sqlDir/$engine/growthexperiments_mentee_data.sql" );
		$updater->addExtensionTable( 'growthexperiments_mentor_mentee',
			"$sqlDir/$engine/growthexperiments_mentor_mentee.sql" );
		$updater->addExtensionTable( 'growthexperiments_user_impact',
			"$sqlDir/$engine/growthexperiments_user_impact.sql" );
		$updater->addExtensionField( 'growthexperiments_link_submissions',
			'gels_anchor_offset',
			"$sqlDir/$engine/patch-add_gels_anchor.sql" );
	}

	/** @inheritDoc */
	public function onUnitTestsAfterDatabaseSetup( $db, $prefix ) {
		global $wgGEDatabaseCluster;

		if ( $wgGEDatabaseCluster ) {
			throw new Exception( 'Cannot use database tests when not on the default cluster' );
		}
	}

}
