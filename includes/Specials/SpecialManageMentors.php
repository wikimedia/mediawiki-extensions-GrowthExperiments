<?php

namespace GrowthExperiments\Specials;

use GrowthExperiments\MentorDashboard\MentorTools\IMentorWeights;
use GrowthExperiments\MentorDashboard\MentorTools\MentorStatusManager;
use GrowthExperiments\Mentorship\Mentor;
use GrowthExperiments\Mentorship\MentorRemover;
use GrowthExperiments\Mentorship\Provider\IMentorWriter;
use GrowthExperiments\Mentorship\Provider\MentorProvider;
use GrowthExperiments\Specials\Forms\ManageMentorsAbstractForm;
use GrowthExperiments\Specials\Forms\ManageMentorsEditMentor;
use GrowthExperiments\Specials\Forms\ManageMentorsRemoveMentor;
use Html;
use HTMLForm;
use Linker;
use LogicException;
use MediaWiki\User\UserEditTracker;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use MWTimestamp;
use OOUI\ButtonWidget;
use PermissionsError;
use SpecialPage;
use Wikimedia\Timestamp\ConvertibleTimestamp;

class SpecialManageMentors extends SpecialPage {

	private UserIdentityLookup $userIdentityLookup;
	private UserEditTracker $userEditTracker;
	private MentorProvider $mentorProvider;
	private IMentorWriter $mentorWriter;
	private MentorStatusManager $mentorStatusManager;
	private MentorRemover $mentorRemover;

	/**
	 * @param UserIdentityLookup $userIdentityLookup
	 * @param UserEditTracker $userEditTracker
	 * @param MentorProvider $mentorProvider
	 * @param IMentorWriter $mentorWriter
	 * @param MentorStatusManager $mentorStatusManager
	 * @param MentorRemover $mentorRemover
	 */
	public function __construct(
		UserIdentityLookup $userIdentityLookup,
		UserEditTracker $userEditTracker,
		MentorProvider $mentorProvider,
		IMentorWriter $mentorWriter,
		MentorStatusManager $mentorStatusManager,
		MentorRemover $mentorRemover
	) {
		parent::__construct( 'ManageMentors' );

		$this->userIdentityLookup = $userIdentityLookup;
		$this->userEditTracker = $userEditTracker;
		$this->mentorProvider = $mentorProvider;
		$this->mentorWriter = $mentorWriter;
		$this->mentorStatusManager = $mentorStatusManager;
		$this->mentorRemover = $mentorRemover;
	}

	/** @inheritDoc */
	protected function getGroupName() {
		return 'growth-tools';
	}

	/**
	 * @inheritDoc
	 */
	public function isIncludable() {
		return true;
	}

	/**
	 * @return bool
	 */
	private function renderInReadOnlyMode(): bool {
		return $this->including() ?? false;
	}

	/**
	 * Can manage mentors?
	 * @return bool
	 */
	private function canManageMentors(): bool {
		return !$this->renderInReadOnlyMode() &&
			ManageMentorsAbstractForm::canManageMentors( $this->getAuthority() );
	}

	/**
	 * @inheritDoc
	 */
	public function getDescription() {
		return $this->msg( 'growthexperiments-manage-mentors-title' );
	}

	/**
	 * @param UserIdentity $user
	 * @return MWTimestamp
	 */
	private function getLastActiveTimestamp( UserIdentity $user ): MWTimestamp {
		return new MWTimestamp( $this->userEditTracker->getLatestEditTimestamp( $user ) );
	}

	private function makeUserLink( UserIdentity $user ) {
		return Linker::userLink(
			$user->getId(),
			$user->getName()
		) . Linker::userToolLinks( $user->getId(), $user->getName() );
	}

	/**
	 * @param Mentor $mentor
	 * @return array{0:string,1:int}
	 */
	private function formatWeight( Mentor $mentor ): array {
		switch ( $mentor->getWeight() ) {
			case IMentorWeights::WEIGHT_NONE:
				$msgKey = 'growthexperiments-mentor-dashboard-mentor-tools-mentor-weight-none';
				break;
			case IMentorWeights::WEIGHT_LOW:
				$msgKey = 'growthexperiments-mentor-dashboard-mentor-tools-mentor-weight-low';
				break;
			case IMentorWeights::WEIGHT_NORMAL:
				$msgKey = 'growthexperiments-mentor-dashboard-mentor-tools-mentor-weight-medium';
				break;
			case IMentorWeights::WEIGHT_HIGH:
				$msgKey = 'growthexperiments-mentor-dashboard-mentor-tools-mentor-weight-high';
				break;
			default:
				throw new LogicException(
					'Weight ' . $mentor->getWeight() . ' is not supported'
				);
		}
		return [ $this->msg( $msgKey )->text(), $mentor->getWeight() ];
	}

	/**
	 * @param Mentor $mentor
	 * @return array{0:string,1:int}
	 */
	private function formatStatus( Mentor $mentor ): array {
		$reason = $this->mentorStatusManager->getAwayReason( $mentor->getUserIdentity() );
		switch ( $reason ) {
			case MentorStatusManager::AWAY_BECAUSE_BLOCK:
			case MentorStatusManager::AWAY_BECAUSE_LOCK:
				return [
					// FIXME use better custom message
					$this->msg( 'blockedtitle' )->text(),
					// XXX: is this the maximum on the frontend?
					PHP_INT_MAX
				];
			case MentorStatusManager::AWAY_BECAUSE_TIMESTAMP:
				$ts = $this->mentorStatusManager->getMentorBackTimestamp( $mentor->getUserIdentity() );
				if ( $ts !== null ) {
					return [
						$this->msg( 'growthexperiments-manage-mentors-status-away-until' )
							->params( $this->getLanguage()->userDate( $ts, $this->getUser() ) )
							->text(),
						(int)ConvertibleTimestamp::convert( TS_UNIX, $ts )
					];
				}
				// if the reason is a timestamp, but we've got no timestamp, just pretend they are active
				// hence no break here
			case null:
				return [
					$this->msg( 'growthexperiments-mentor-dashboard-mentor-tools-mentor-status-active' )
						->text(),
					-1
				];
			default:
				throw new LogicException( "Reason for absence \"$reason\" is not supported" );
		}
	}

	/**
	 * @param Mentor $mentor
	 * @param int $i
	 * @return string
	 */
	private function getMentorAsHtmlRow( Mentor $mentor, int $i ): string {
		[ $weightText, $weightRank ] = $this->formatWeight( $mentor );
		[ $statusText, $statusRank ] = $this->formatStatus( $mentor );
		$ts = $this->getLastActiveTimestamp( $mentor->getUserIdentity() );

		$items = [
			Html::element( 'td', [], (string)$i ),
			Html::rawElement(
				'td',
				[ 'data-sort-value' => $mentor->getUserIdentity()->getName() ],
				$this->makeUserLink( $mentor->getUserIdentity() )
			),
			Html::element(
				'td',
				[ 'data-sort-value' => $ts->getTimestamp( TS_UNIX ) ],
				$this->getContext()->getLanguage()->userTimeAndDate( $ts, $this->getUser() )
			),
			Html::element( 'td', [ 'data-sort-value' => $weightRank ], $weightText ),
			Html::element( 'td', [ 'data-sort-value' => $statusRank ], $statusText ),
			Html::element( 'td', [], $mentor->getIntroText() ),
		];
		if ( $this->canManageMentors() ) {
			$items[] = Html::rawElement( 'td', [], new ButtonWidget( [
				'label' => $this->msg( 'growthexperiments-manage-mentors-edit' )->text(),
				'href' => SpecialPage::getTitleFor(
					'ManageMentors',
					'edit-mentor/' . $mentor->getUserIdentity()->getId()
				)->getLocalURL(),
				'flags' => [ 'primary', 'progressive' ],
			] ) );
			$items[] = Html::rawElement( 'td', [], new ButtonWidget( [
				'label' => $this->msg( 'growthexperiments-manage-mentors-remove-mentor' )->text(),
				'href' => SpecialPage::getTitleFor(
					'ManageMentors',
					'remove-mentor/' . $mentor->getUserIdentity()->getId()
				)->getLocalURL(),
				'flags' => [ 'primary', 'destructive' ]
			] ) );
		}

		return Html::rawElement(
			'tr',
			[],
			implode( "\n", $items )
		);
	}

	/**
	 * @param string[] $mentorNames
	 * @return string
	 */
	private function getMentorsTableBody( array $mentorNames ): string {
		// sort mentors alphabetically
		sort( $mentorNames );

		$mentorsHtml = [];
		$i = 1;
		foreach ( $mentorNames as $mentorName ) {
			$mentorUser = $this->userIdentityLookup->getUserIdentityByName( $mentorName );
			if ( !$mentorUser ) {
				// TODO: Log an error?
				continue;
			}

			$mentorsHtml[] = $this->getMentorAsHtmlRow(
				$this->mentorProvider->newMentorFromUserIdentity( $mentorUser ),
				$i
			);
			$i++;
		}

		return implode( "\n", $mentorsHtml );
	}

	/**
	 * @param string[] $mentorNames
	 * @return string
	 */
	private function getMentorsTable( array $mentorNames ): string {
		if ( $mentorNames === [] ) {
			return Html::element(
				'p',
				[],
				$this->msg( 'growthexperiments-manage-mentors-none' )->text()
			);
		}

		$headerItems = [
			Html::element( 'th', [], '#' ),
			Html::element(
				'th',
				[],
				$this->msg( 'growthexperiments-manage-mentors-username' )->text()
			),
			Html::element(
				'th',
				// unix timestamp
				[ 'data-sort-type' => 'number' ],
				$this->msg( 'growthexperiments-manage-mentors-last-active' )->text()
			),
			Html::element(
				'th',
				[ 'data-sort-type' => 'number' ],
				$this->msg( 'growthexperiments-manage-mentors-weight' )->text()
			),
			Html::element(
				'th',
				[ 'data-sort-type' => 'number' ],
				$this->msg( 'growthexperiments-manage-mentors-status' )->text()
			),
			Html::element(
				'th',
				[ 'class' => 'unsortable' ],
				$this->msg( 'growthexperiments-manage-mentors-intro-msg' )->text()
			),
		];

		if ( $this->canManageMentors() ) {
			$headerItems[] = Html::element(
				'th',
				[ 'class' => 'unsortable' ],
				$this->msg( 'growthexperiments-manage-mentors-edit' )->text()
			);
			$headerItems[] = Html::element(
				'th',
				[ 'class' => 'unsortable' ],
				$this->msg( 'growthexperiments-manage-mentors-remove-mentor' )->text()
			);
		}

		return Html::rawElement(
			'table',
			[
				'class' => 'wikitable sortable'
			],
			implode( "\n", [
				Html::rawElement(
					'thead',
					[],
					Html::rawElement(
						'tr',
						[],
						implode( "\n", $headerItems )
					)
				),
				Html::rawElement(
					'tbody',
					[],
					$this->getMentorsTableBody( $mentorNames )
				)
			] )
		);
	}

	/**
	 * @param string $action
	 * @param UserIdentity $mentorUser
	 * @return HTMLForm|null
	 */
	private function getFormByAction( string $action, UserIdentity $mentorUser ): ?HTMLForm {
		switch ( $action ) {
			case 'remove-mentor':
				return new ManageMentorsRemoveMentor(
					$this->mentorRemover,
					$mentorUser,
					$this->getContext()
				);
			case 'edit-mentor':
				return new ManageMentorsEditMentor(
					$this->mentorProvider,
					$this->mentorWriter,
					$this->mentorStatusManager,
					$mentorUser,
					$this->getContext()
				);
			default:
				return null;
		}
	}

	private function parseSubpage( ?string $par ): ?array {
		if ( !$par || strpos( $par, '/' ) === false ) {
			return null;
		}

		[ $action, $data ] = explode( '/', $par, 2 );
		$mentorUserId = (int)$data;
		if ( !$mentorUserId ) {
			return null;
		}

		return [
			$action,
			$this->userIdentityLookup->getUserIdentityByUserId( $mentorUserId )
		];
	}

	private function handleAction( ?string $par ): bool {
		[ $action, $mentorUser ] = $this->parseSubpage( $par );

		if ( !$action ) {
			return false;
		}

		if ( !$this->canManageMentors() ) {
			throw new PermissionsError( 'managementors' );
		}

		if ( !$mentorUser ) {
			$this->getOutput()->addHTML( Html::element(
				'p',
				[ 'class' => 'error' ],
				$this->msg(
					'growthexperiments-manage-mentors-error-no-such-user'
				)->text()
			) );
			return true;
		}

		$form = $this->getFormByAction( $action, $mentorUser );
		if ( !$form ) {
			return false;
		}

		$form->show();
		return true;
	}

	/**
	 * @return string
	 */
	private function makePreHTML(): string {
		if ( $this->including() ) {
			// included version should only include the table
			return '';
		}

		$howToChangeMessageKey = $this->canManageMentors()
			? 'growthexperiments-manage-mentors-pretext-privileged'
			: 'growthexperiments-manage-mentors-pretext-regular';

		return Html::rawElement(
			'div',
			[],
			implode( "\n", [
				Html::rawElement(
					'p',
					[],
					implode( "\n", [
						$this->msg( 'growthexperiments-manage-mentors-pretext-purpose' )->parse(),
						$this->msg( $howToChangeMessageKey )->parse(),
						$this->msg( 'growthexperiments-manage-mentors-pretext-stored-at' )
							->params( $this->getConfig()->get( 'GEStructuredMentorList' ) )
							->parse(),
					] )
				),
				Html::rawElement(
					'p',
					[],
					$this->msg( 'growthexperiments-manage-mentors-pretext-to-enroll' )->parse()
				)
			] )
		);
	}

	/**
	 * @param string $text
	 * @return string
	 */
	private function makeHeadlineElement( string $text ): string {
		return Html::element(
			$this->including() ? 'h3' : 'h2',
			[],
			$text
		);
	}

	/**
	 * @inheritDoc
	 */
	public function execute( $subPage ) {
		parent::execute( $subPage );
		if ( $this->handleAction( $subPage ) ) {
			return;
		}

		$out = $this->getOutput();
		// We only need OOUI (ButtonWidget) when can manage mentors
		// Avoid access to the global context when transcluding (T346760)
		if ( $this->canManageMentors() ) {
			$out->enableOOUI();
		}
		$out->addHTML( implode( "\n", [
			$this->makePreHTML(),
			$this->makeHeadlineElement( $this->msg( 'growthexperiments-manage-mentors-auto-assigned' )->text() ),
			Html::element( 'p', [],
				$this->msg( 'growthexperiments-manage-mentors-auto-assigned-text' )->text()
			),
			$this->getMentorsTable( $this->mentorProvider->getAutoAssignedMentors() ),
			$this->makeHeadlineElement( $this->msg( 'growthexperiments-manage-mentors-manually-assigned' )->text() ),
			Html::element( 'p', [],
				$this->msg( 'growthexperiments-manage-mentors-manually-assigned-text' )->text()
			),
			$this->getMentorsTable( $this->mentorProvider->getManuallyAssignedMentors() ),
		] ) );
	}
}
