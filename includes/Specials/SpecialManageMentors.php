<?php

namespace GrowthExperiments\Specials;

use GrowthExperiments\MentorDashboard\MentorTools\IMentorWeights;
use GrowthExperiments\MentorDashboard\MentorTools\MentorStatusManager;
use GrowthExperiments\Mentorship\Mentor;
use GrowthExperiments\Mentorship\MentorRemover;
use GrowthExperiments\Mentorship\Provider\IMentorWriter;
use GrowthExperiments\Mentorship\Provider\MentorProvider;
use GrowthExperiments\Specials\Forms\ManageMentorsAbstractForm;
use GrowthExperiments\Specials\Forms\ManageMentorsAddMentor;
use GrowthExperiments\Specials\Forms\ManageMentorsEditMentor;
use GrowthExperiments\Specials\Forms\ManageMentorsRemoveMentor;
use LogicException;
use MediaWiki\Config\Config;
use MediaWiki\Exception\PermissionsError;
use MediaWiki\Html\Html;
use MediaWiki\Linker\Linker;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\User\UserEditTracker;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\Utils\MWTimestamp;
use OOUI\ButtonWidget;
use Wikimedia\Timestamp\ConvertibleTimestamp;

class SpecialManageMentors extends SpecialPage {

	private UserIdentityLookup $userIdentityLookup;
	private UserEditTracker $userEditTracker;
	private MentorProvider $mentorProvider;
	private IMentorWriter $mentorWriter;
	private MentorStatusManager $mentorStatusManager;
	private MentorRemover $mentorRemover;
	private Config $wikiConfig;

	/**
	 * @param UserIdentityLookup $userIdentityLookup
	 * @param UserEditTracker $userEditTracker
	 * @param MentorProvider $mentorProvider
	 * @param IMentorWriter $mentorWriter
	 * @param MentorStatusManager $mentorStatusManager
	 * @param MentorRemover $mentorRemover
	 * @param Config $wikiConfig
	 */
	public function __construct(
		UserIdentityLookup $userIdentityLookup,
		UserEditTracker $userEditTracker,
		MentorProvider $mentorProvider,
		IMentorWriter $mentorWriter,
		MentorStatusManager $mentorStatusManager,
		MentorRemover $mentorRemover,
		Config $wikiConfig
	) {
		parent::__construct( 'ManageMentors' );

		$this->userIdentityLookup = $userIdentityLookup;
		$this->userEditTracker = $userEditTracker;
		$this->mentorProvider = $mentorProvider;
		$this->mentorWriter = $mentorWriter;
		$this->mentorStatusManager = $mentorStatusManager;
		$this->mentorRemover = $mentorRemover;
		$this->wikiConfig = $wikiConfig;
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

	private function renderInReadOnlyMode(): bool {
		return $this->including() ?? false;
	}

	/**
	 * Can manage mentors?
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

	private function getLastActiveTimestamp( UserIdentity $user ): MWTimestamp {
		return new MWTimestamp( $this->userEditTracker->getLatestEditTimestamp( $user ) );
	}

	private function makeUserLink( UserIdentity $user ): string {
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
	 * @param UserIdentity[] $mentors
	 * @return string
	 */
	private function getMentorsTableBody( array $mentors ): string {
		// sort mentors alphabetically
		usort(
			$mentors,
			static fn ( UserIdentity $a, UserIdentity $b ) => $a->getName() <=> $b->getName()
		);

		$mentorsHtml = [];
		$i = 1;
		foreach ( $mentors as $mentor ) {
			$mentorsHtml[] = $this->getMentorAsHtmlRow(
				$this->mentorProvider->newMentorFromUserIdentity( $mentor ),
				$i
			);
			$i++;
		}

		return implode( "\n", $mentorsHtml );
	}

	/**
	 * @param UserIdentity[] $mentors
	 * @return string
	 */
	private function getMentorsTable( array $mentors ): string {
		if ( $mentors === [] ) {
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
					$this->getMentorsTableBody( $mentors )
				)
			] )
		);
	}

	/**
	 * @param string $action
	 * @param UserIdentity|null $mentorUser
	 * @return ManageMentorsAbstractForm|null
	 */
	private function getFormByAction( string $action, ?UserIdentity $mentorUser ): ?ManageMentorsAbstractForm {
		switch ( $action ) {
			case 'remove-mentor':
				return new ManageMentorsRemoveMentor(
					$this->mentorRemover,
					$mentorUser,
					$this->getContext()
				);
			case 'add-mentor':
				return new ManageMentorsAddMentor(
					$this->userIdentityLookup,
					$this->mentorProvider,
					$this->mentorWriter,
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
		if ( !$par ) {
			return null;
		}

		$explodeResult = explode( '/', $par, 2 );
		if ( count( $explodeResult ) === 2 ) {
			[ $action, $data ] = $explodeResult;
		} else {
			[ $action, $data ] = [ $explodeResult[0], null ];
		}
		$mentorUserId = (int)$data;
		if ( !$mentorUserId ) {
			return [ $action, null ];
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

		if ( $mentorUser === null && $action !== 'add-mentor' ) {
			// All forms besides add-mentor require a valid $mentorUser
			$this->getOutput()->addHTML( Html::element(
				'p',
				[ 'class' => 'error' ],
				$this->msg(
					'growthexperiments-manage-mentors-error-no-such-user',
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
	 * Display the mentorship warning message if Mentorship is not enabled.
	 * @return string Display HTML for the warning message.
	 */
	private function displayMentorshipWarningMessage(): string {
		$configPage = SpecialPage::getTitleFor( 'CommunityConfiguration', 'Mentorship' )
			->getPrefixedText();

		return Html::warningBox(
			$this->msg( 'growthexperiments-mentor-dashboard-mentorship-disabled-with-link' )
				->params( $configPage )
				->parse(),
			'ext-growthExperiments-message--warning'
		);
	}

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

		if ( !$this->wikiConfig->get( 'GEMentorshipEnabled' ) ) {
			$out->addModuleStyles( [ 'codex-styles' ] );
			$out->addHTML( $this->displayMentorshipWarningMessage() );
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
			$this->canManageMentors() ? new ButtonWidget( [
				'label' => $this->msg( 'growhtexperiments-manage-mentors-add-mentor' )->text(),
				'href' => SpecialPage::getTitleFor(
					'ManageMentors',
					'add-mentor'
				)->getLocalURL(),
				'flags' => [ 'primary', 'progressive' ],
			] ) : '',
		] ) );
	}
}
