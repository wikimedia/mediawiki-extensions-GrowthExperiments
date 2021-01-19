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

		$extensionRoot = __DIR__ . '/..';
		$engine = $updater->getDB()->getType();
		$updater->addExtensionTable( 'growthexperiments_link_recommendations',
			"$extensionRoot/maintenance/schemas/$engine/growthexperiments_link_recommendations.sql" );
		$updater->addExtensionTable( 'growthexperiments_link_submissions',
			"$extensionRoot/maintenance/schemas/$engine/growthexperiments_link_submissions.sql" );
		$updater->addExtensionField( 'growthexperiments_link_submissions',
			'gels_anchor_offset',
			"$extensionRoot/maintenance/schemaChanges/$engine/patch-add_gels_anchor.sql" );
	}

	/** @inheritDoc */
	public function onUnitTestsAfterDatabaseSetup( $db, $prefix ) {
		global $wgGEDatabaseCluster;

		if ( $wgGEDatabaseCluster ) {
			throw new Exception( 'Cannot use database tests when not on the default cluster' );
		}
	}

}
