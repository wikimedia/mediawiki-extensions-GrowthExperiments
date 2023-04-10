<?php

namespace GrowthExperiments\EventLogging;

use EventLogging;
use ExtensionRegistry;
use GrowthExperiments\MentorDashboard\PersonalizedPraise\PersonalizedPraiseSettings;
use MediaWiki\User\UserIdentity;
use WikiMap;

class PersonalizedPraiseLogger {
	/** @var string Versioned schema URL for $schema field */
	private const SCHEMA_VERSIONED = '/analytics/mediawiki/mentor_dashboard/personalized_praise/1.0.0';
	/** @var string Stream name for EventLogging::submit */
	private const STREAM = 'mediawiki.mentor_dashboard.personalized_praise';

	public const ACTION_SUGGESTED = 'suggested';
	public const ACTION_NOTIFIED = 'notified';
	public const ACTION_PRAISED = 'praised';

	private PersonalizedPraiseSettings $personalizedPraiseSettings;

	/**
	 * @param PersonalizedPraiseSettings $personalizedPraiseSettings
	 */
	public function __construct( PersonalizedPraiseSettings $personalizedPraiseSettings ) {
		$this->personalizedPraiseSettings = $personalizedPraiseSettings;
	}

	private function isEventLoggingAvailable(): bool {
		return ExtensionRegistry::getInstance()->isLoaded( 'EventLogging' );
	}

	/**
	 * Log an event to Personalized praise's stream
	 *
	 * @param string $action
	 * @param UserIdentity $mentor
	 * @param UserIdentity|null $mentee
	 * @param array $additionalData
	 */
	private function doLog(
		string $action,
		UserIdentity $mentor,
		?UserIdentity $mentee,
		array $additionalData = []
	): void {
		$additionalDataSerialized = [];
		foreach ( $additionalData as $key => $value ) {
			$additionalDataSerialized[] = sprintf( '%s=%s', $key, $value );
		}

		EventLogging::submit(
			self::STREAM,
			[
				'$schema' => self::SCHEMA_VERSIONED,
				'database' => WikiMap::getCurrentWikiId(),
				'action' => $action,
				'action_data' => implode( ';', $additionalDataSerialized ),
				'mentor_id' => $mentor->getId(),
				'mentee_id' => $mentee ? $mentee->getId() : null,
			]
		);
	}

	/**
	 * @param UserIdentity $mentor
	 * @param UserIdentity $mentee
	 * @param bool $wasNotified
	 */
	public function logSuggested(
		UserIdentity $mentor, UserIdentity $mentee, bool $wasNotified
	): void {
		if ( $this->isEventLoggingAvailable() ) {
			$this->doLog(
				self::ACTION_SUGGESTED,
				$mentor,
				$mentee,
				[
					'notification_policy' => $this->personalizedPraiseSettings->getNotificationsFrequency(
						$mentor
					),
					'was_notified' => $wasNotified,
				]
			);
		}
	}

	/**
	 * @param UserIdentity $mentor
	 */
	public function logNotified( UserIdentity $mentor ): void {
		if ( $this->isEventLoggingAvailable() ) {
			$this->doLog(
				self::ACTION_NOTIFIED,
				$mentor,
				null,
				[
					'notification_policy' => $this->personalizedPraiseSettings->getNotificationsFrequency(
						$mentor
					)
				]
			);
		}
	}

	/**
	 * @param UserIdentity $mentor
	 * @param UserIdentity $mentee
	 */
	public function logPraised( UserIdentity $mentor, UserIdentity $mentee ): void {
		if ( $this->isEventLoggingAvailable() ) {
			$this->doLog(
				self::ACTION_PRAISED,
				$mentor,
				$mentee
			);
		}
	}
}
