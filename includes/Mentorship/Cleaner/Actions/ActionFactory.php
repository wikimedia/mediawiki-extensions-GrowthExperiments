<?php

namespace GrowthExperiments\Mentorship\Cleaner\Actions;

use GrowthExperiments\MentorDashboard\MentorTools\MentorStatusManager;
use GrowthExperiments\Mentorship\Cleaner\LastActionTimestampLookup;
use GrowthExperiments\Mentorship\MentorRemover;
use GrowthExperiments\Mentorship\Provider\IMentorWriter;
use GrowthExperiments\Mentorship\Provider\MentorProvider;
use InvalidArgumentException;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentity;

class ActionFactory {

	/**
	 * @var string[] Class actions referencing an IAction implementation
	 * @note When adding an action here, remember to update newFromClassName with the action
	 * construction wiring.
	 * @see IAction
	 * @see self::newFromClassName()
	 */
	public const ACTIONS = [
		MarkMentorAsAwayAction::class,
		RemoveMentorAction::class,
	];

	/**
	 * @internal only for use by ServiceWiring
	 */
	public const CONSTRUCTOR_OPTIONS = [
		'GEMentorshipShouldBeAutoawayed',
		'GEMentorshipAutoawayedAfterDays',
		'GEMentorshipShouldBeAutoremoved',
		'GEMentorshipAutoremovedAfterDays',
	];

	/** @var IAction[] Map of class name => instance of action */
	private array $instanceCache = [];

	public function __construct(
		private ServiceOptions $options,
		private MentorProvider $mentorProvider,
		private IMentorWriter $mentorWriter,
		private MentorStatusManager $mentorStatusManager,
		private MentorRemover $mentorRemover,
		private LastActionTimestampLookup $lastActionTimestampLookup,
	) {
		$this->options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
	}

	private function getSystemPerformer(): UserIdentity {
		return User::newSystemUser( User::MAINTENANCE_SCRIPT_USER, [ 'steal' => true ] );
	}

	private function getAwayDurationInDays(): int {
		if ( !$this->options->get( 'GEMentorshipShouldBeAutoremoved' ) ) {
			// Month is a good enough default
			return 30;
		}

		// If autoremoval is enabled, ensure the user would be removed if inactivity continues.
		return (int)$this->options->get( 'GEMentorshipAutoremovedAfterDays' ) -
			(int)$this->options->get( 'GEMentorshipAutoawayedAfterDays' ) + 1;
	}

	/**
	 * Create an action
	 *
	 * @note When updating this method, remember to update ACTIONS constant as well.
	 * @see self::ACTIONS
	 * @param string $class Class name of an IAction to construct
	 * @return IAction
	 */
	public function newFromClassName( string $class ): IAction {
		if ( !array_key_exists( $class, $this->instanceCache ) ) {
			$this->instanceCache[$class] = match ( $class ) {
				MarkMentorAsAwayAction::class => new MarkMentorAsAwayAction(
					$this->mentorProvider,
					$this->mentorWriter,
					$this->mentorStatusManager,
					$this->lastActionTimestampLookup,
					$this->getSystemPerformer(),
					(bool)$this->options->get( 'GEMentorshipShouldBeAutoawayed' ),
					(int)$this->options->get( 'GEMentorshipAutoawayedAfterDays' ),
					$this->getAwayDurationInDays(),
				),
				RemoveMentorAction::class => new RemoveMentorAction(
					$this->mentorRemover,
					$this->lastActionTimestampLookup,
					$this->getSystemPerformer(),
					(bool)$this->options->get( 'GEMentorshipShouldBeAutoremoved' ),
					(int)$this->options->get( 'GEMentorshipAutoremovedAfterDays' ),
				),
				default => throw new InvalidArgumentException( $class . ' is not an action' )
			};
		}

		return $this->instanceCache[$class];
	}
}
