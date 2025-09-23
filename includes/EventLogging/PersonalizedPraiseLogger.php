<?php

namespace GrowthExperiments\EventLogging;

use GrowthExperiments\MentorDashboard\PersonalizedPraise\PersonalizedPraiseSettings;
use MediaWiki\Extension\EventLogging\EventLogging;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\User\UserIdentity;
use MediaWiki\WikiMap\WikiMap;

class PersonalizedPraiseLogger {
	/** @var string Versioned schema URL for $schema field */
	private const SCHEMA_VERSIONED = '/analytics/mediawiki/mentor_dashboard/personalized_praise/1.0.2';
	/** @var string Stream name for EventLogging::submit */
	private const STREAM = 'mediawiki.mentor_dashboard.personalized_praise';

	public const ACTION_SUGGESTED = 'suggested';
	public const ACTION_NOTIFIED = 'notified';
	public const ACTION_PRAISED = 'praised';
	public const ACTION_SKIPPED = 'skipped';

	private PersonalizedPraiseSettings $personalizedPraiseSettings;

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

		$eventData = [
			'$schema' => self::SCHEMA_VERSIONED,
			'database' => WikiMap::getCurrentWikiId(),
			'action' => $action,
			'action_data' => implode( ';', $additionalDataSerialized ),
			'mentor_id' => $mentor->getId(),
		];
		if ( $mentee ) {
			$eventData['mentee_id'] = $mentee->getId();
		}

		EventLogging::submit( self::STREAM, $eventData );
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

	public function logNotified( UserIdentity $mentor ): void {
		if ( $this->isEventLoggingAvailable() ) {
			$this->doLog(
				self::ACTION_NOTIFIED,
				$mentor,
				null,
				[
					'notification_policy' => $this->personalizedPraiseSettings->getNotificationsFrequency(
						$mentor
					),
				]
			);
		}
	}

	public function logPraised( UserIdentity $mentor, UserIdentity $mentee ): void {
		if ( $this->isEventLoggingAvailable() ) {
			$this->doLog(
				self::ACTION_PRAISED,
				$mentor,
				$mentee
			);
		}
	}

	/**
	 * @param UserIdentity $mentor
	 * @param UserIdentity $mentee
	 * @param string $skipReason
	 */
	public function logSkipped(
		UserIdentity $mentor,
		UserIdentity $mentee,
		string $skipReason
	): void {
		if ( $this->isEventLoggingAvailable() ) {
			$this->doLog(
				self::ACTION_SKIPPED,
				$mentor,
				$mentee,
				[
					'skip_reason' => $skipReason,
				]
			);
		}
	}
}
