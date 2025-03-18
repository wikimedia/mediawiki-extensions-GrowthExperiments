<?php

namespace GrowthExperiments\EventLogging;

use MediaWiki\Extension\EventLogging\EventLogging;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\User\User;
use MediaWiki\WikiMap\WikiMap;

class GrowthExperimentsInteractionLogger {
	/** @var string Versioned schema URL for $schema field */
	private const SCHEMA_VERSIONED = '/analytics/product_metrics/web/base/1.3.1';
	/** @var string Stream name for EventLogging::submit */
	private const STREAM = 'mediawiki.product_metrics.growth_product_interaction';
	/** @var string Unique identifier for the single experiment name used for
	 * the "experiments.enrolled" array  in the event data and as key for the
	 * "experiments.assigned" object
	 */
	public const GROWTH_EXPERIMENTS_EXPERIMENT_ID = 'growth-experiments';

	private function isEventLoggingAvailable(): bool {
		return ExtensionRegistry::getInstance()->isLoaded( 'EventLogging' );
	}

	/**
	 * Log an event to Growth's product interaction stream
	 *
	 * @param User $user
	 * @param string $action
	 * @param array $additionalData
	 */
	public function log( User $user, string $action, array $additionalData = [] ): void {
		if ( !$this->isEventLoggingAvailable() ) {
			return;
		}
		[
			'variant' => $variant,
			'action_source' => $actionSource
		] = $additionalData;
		$wiki = WikiMap::getCurrentWikiId();
		// FIXME override http.request_headers.user-agent to reduce data collection risk tier. This should
		// be done via stream configs or instrument config. It's done as an override because the setting control
		// is only supported for JS clients, see T385180
		$eventData = [
			'http' => [
				'request_headers' => [
					'user-agent' => ''
				]
			],
			'mediawiki' => [
				'database' => $wiki
			],
			'$schema' => self::SCHEMA_VERSIONED,
			'action' => $action,
			// "agent" is a required property in product_metrics/web/base/1.3.0 schema
			'agent' => [
				'client_platform' => 'mediawiki_php'
			],
			'action_source' => $actionSource,
			'performer' => [
				'id' => $user->getId(),
				'edit_count' => $user->getEditCount()
			],
			'experiments' => [
				'enrolled' => [ self::GROWTH_EXPERIMENTS_EXPERIMENT_ID ],
				'assigned' => [
					self::GROWTH_EXPERIMENTS_EXPERIMENT_ID => $variant
				],
			]
		];

		EventLogging::submit( self::STREAM, $eventData );
	}
}
