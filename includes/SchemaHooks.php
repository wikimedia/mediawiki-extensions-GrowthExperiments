<?php

namespace GrowthExperiments;

use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;

/**
 * LoadExtensionSchemaUpdates hook handler.
 * This hook handler must not have any service dependencies.
 */
class SchemaHooks implements LoadExtensionSchemaUpdatesHook {

	public const VIRTUAL_DOMAIN = 'virtual-growthexperiments';
	private const TABLES = [
		'growthexperiments_link_recommendations', 'growthexperiments_link_submissions',
		'growthexperiments_mentee_data', 'growthexperiments_mentor_mentee',
		'growthexperiments_user_impact',
	];

	/** @inheritDoc */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$sqlDir = __DIR__ . '/../sql/' . $updater->getDB()->getType();

		foreach ( self::TABLES as $tableName ) {
			$updater->addExtensionUpdateOnVirtualDomain( [
				self::VIRTUAL_DOMAIN, 'addTable',
				$tableName, "$sqlDir/$tableName.sql", true,
			] );
		}

		$updater->addExtensionUpdateOnVirtualDomain( [
			self::VIRTUAL_DOMAIN, 'addField',
			'growthexperiments_link_submissions', 'gels_anchor_offset',
			"$sqlDir/patch-add_gemm_mentee_is_active.sql", true,
		] );
		$updater->addExtensionUpdateOnVirtualDomain( [
			self::VIRTUAL_DOMAIN, 'addField',
			'growthexperiments_mentor_mentee', 'gemm_mentee_is_active',
			"$sqlDir/patch-add_gemm_mentee_is_active.sql", true,
		] );
		$updater->addExtensionUpdateOnVirtualDomain( [
			self::VIRTUAL_DOMAIN, 'modifyField',
			'growthexperiments_link_recommendations', 'gelr_data',
			"$sqlDir/patch-modify_gelr_data_nullable.sql", true,
		] );
		$updater->addExtensionUpdateOnVirtualDomain( [
			self::VIRTUAL_DOMAIN, 'modifyField',
			'growthexperiments_mentor_mentee', 'gemm_mentee_is_active',
			"$sqlDir/patch-modify_gemm_mentee_is_active_mwtinyint.sql", true,
		] );
	}

}
