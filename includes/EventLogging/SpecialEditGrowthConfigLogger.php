<?php

namespace GrowthExperiments\EventLogging;

use DeferredUpdates;
use ExtensionRegistry;
use GrowthExperiments\Specials\SpecialEditGrowthConfig;
use InvalidArgumentException;
use MediaWiki\Extension\EventLogging\EventLogging;
use MediaWiki\Permissions\Authority;
use WikiMap;

class SpecialEditGrowthConfigLogger {

	/** @var string Versioned schema URL for $schema field */
	private const SCHEMA_VERSIONED = '/analytics/mediawiki/editgrowthconfig/1.0.2';

	/** @var string Stream name for EventLogging::submit */
	private const STREAM = 'mediawiki.editgrowthconfig';

	/** @var string */
	public const ACTION_VIEW = 'view';

	/** @var string */
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
				'is_registered_user' => $authority->isRegistered(),
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
				'is_registered_user' => $authority->isRegistered(),
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
