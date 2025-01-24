<?php

namespace GrowthExperiments\EventLogging;

use GrowthExperiments\Specials\SpecialEditGrowthConfig;
use GrowthExperiments\Util;
use InvalidArgumentException;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Extension\EventLogging\EventLogging;
use MediaWiki\Permissions\Authority;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\WikiMap\WikiMap;

class SpecialEditGrowthConfigLogger {

	/** Versioned schema URL for $schema field */
	private const SCHEMA_VERSIONED = '/analytics/mediawiki/editgrowthconfig/1.0.2';

	/** Stream name for EventLogging::submit */
	private const STREAM = 'mediawiki.editgrowthconfig';

	public const ACTION_VIEW = 'view';
	public const ACTION_SAVE = 'save';

	/**
	 * Log an `view` event
	 *
	 * @param Authority $authority
	 */
	private function doLogView(
		Authority $authority
	): void {
		EventLogging::submit(
			self::STREAM,
			[
				'$schema' => self::SCHEMA_VERSIONED,
				'database' => WikiMap::getCurrentWikiId(),
				'action' => self::ACTION_VIEW,
				'is_privileged_user' => $authority->isAllowed(
					SpecialEditGrowthConfig::REQUIRED_RIGHT_TO_WRITE
				),
				'is_registered_user' => $authority->isNamed(),
			]
		);
	}

	/**
	 * Log a `save` interaction
	 *
	 * @param Authority $authority
	 */
	private function doLogSave(
		Authority $authority
	): void {
		EventLogging::submit(
			self::STREAM,
			[
				'$schema' => self::SCHEMA_VERSIONED,
				'database' => WikiMap::getCurrentWikiId(),
				'action' => self::ACTION_SAVE,
				'is_privileged_user' => $authority->isAllowed(
					SpecialEditGrowthConfig::REQUIRED_RIGHT_TO_WRITE
				),
				'is_registered_user' => $authority->isNamed(),
				'performer' => [
					'user_id' => $authority->getUser()->getId(),
					'user_text' => $authority->getUser()->getName(),
				],
			]
		);
	}

	/**
	 * If EventLogging is enabled, log an SpecialEditGrowthConfig-related action
	 *
	 * @param string $action One of ACTION_* constants
	 * @param Authority $authority
	 */
	public function logAction(
		string $action,
		Authority $authority
	): void {
		if ( Util::useCommunityConfiguration() ) {
			wfDeprecated( __METHOD__, '1.44', 'GrowthExperiments' );
		}

		if ( ExtensionRegistry::getInstance()->isLoaded( 'EventLogging' ) ) {
			DeferredUpdates::addCallableUpdate( function () use ( $action, $authority ) {
				switch ( $action ) {
					case self::ACTION_VIEW:
						$this->doLogView( $authority );
						break;
					case self::ACTION_SAVE:
						$this->doLogSave( $authority );
						break;
					default:
						throw new InvalidArgumentException( 'Unsupported value passed as $action' );
				}
			} );
		}
	}
}
