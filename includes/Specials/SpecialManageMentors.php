<?php

namespace GrowthExperiments\Specials;

use GrowthExperiments\Mentorship\Mentor;
use GrowthExperiments\Mentorship\Provider\IMentorWriter;
use GrowthExperiments\Mentorship\Provider\MentorProvider;
use Html;
use HTMLForm;
use Linker;
use MediaWiki\User\UserEditTracker;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use MWTimestamp;
use OOUI\ButtonWidget;
use SpecialPage;
use Status;

class SpecialManageMentors extends SpecialPage {

	/** @var UserIdentityLookup */
	private $userIdentityLookup;

	/** @var UserEditTracker */
	private $userEditTracker;

	/** @var MentorProvider */
	private $mentorProvider;

	/** @var IMentorWriter */
	private $mentorWriter;

	/**
	 * @param UserIdentityLookup $userIdentityLookup
	 * @param UserEditTracker $userEditTracker
	 * @param MentorProvider $mentorProvider
	 * @param IMentorWriter $mentorWriter
	 */
	public function __construct(
		UserIdentityLookup $userIdentityLookup,
		UserEditTracker $userEditTracker,
		MentorProvider $mentorProvider,
		IMentorWriter $mentorWriter
	) {
		parent::__construct( 'ManageMentors' );

		$this->userIdentityLookup = $userIdentityLookup;
		$this->userEditTracker = $userEditTracker;
		$this->mentorProvider = $mentorProvider;
		$this->mentorWriter = $mentorWriter;
	}

	/**
	 * Can manage mentors?
	 * @return bool
	 */
	private function canManageMentors(): bool {
		return $this->getUser()->isAllowed( 'managementors' );
	}

	/**
	 * @return string
	 */
	public function getDescription() {
		return $this->msg( 'growthexperiments-manage-mentors-title' )->text();
	}

	/**
	 * @param UserIdentity $user
	 * @return string
	 */
	private function getLastActiveTimestamp( UserIdentity $user ): string {
		$timestamp = new MWTimestamp( $this->userEditTracker->getLatestEditTimestamp( $user ) );
		$timestamp->offsetForUser( $this->getUser() );

		return $this->getContext()->getLanguage()->timeanddate(
			$timestamp
		);
	}

	private function makeUserLink( UserIdentity $user ) {
		return Linker::userLink(
			$user->getId(),
			$user->getName()
		) . Linker::userToolLinks( $user->getId(), $user->getName() );
	}

	/**
	 * @param Mentor $mentor
	 * @param int $i
	 * @return string
	 */
	private function getMentorAsHtmlRow( Mentor $mentor, int $i ): string {
		$items = [
			Html::element( 'td', [], (string)$i ),
			Html::rawElement( 'td', [], $this->makeUserLink( $mentor->getUserIdentity() ) ),
			Html::element( 'td', [], $this->getLastActiveTimestamp( $mentor->getUserIdentity() ) ),
			Html::element( 'td', [], $mentor->getIntroText() ),
		];
		if ( $this->canManageMentors() ) {
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
		$headerItems = [
			Html::element( 'th', [], '#' ),
			Html::element(
				'th',
				[],
				$this->msg( 'growthexperiments-manage-mentors-username' )->text()
			),
			Html::element(
				'th',
				[],
				$this->msg( 'growthexperiments-manage-mentors-last-active' )->text()
			),
			Html::element(
				'th',
				[],
				$this->msg( 'growthexperiments-manage-mentors-intro-msg' )->text()
			)
		];

		if ( $this->canManageMentors() ) {
			$headerItems[] = Html::element(
				'th',
				[],
				$this->msg( 'growthexperiments-manage-mentors-remove-mentor' )->text()
			);
		}

		return Html::rawElement(
			'table',
			[
				'class' => 'wikitable'
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
	 * @param UserIdentity $mentorUser Mentor that is being removed
	 * @return array
	 */
	private function getManageMentorsFormFields( UserIdentity $mentorUser ): array {
		return [
			'reason' => [
				'type' => 'text',
				'label-message' => 'growthexperiments-manage-mentors-remove-mentor-reason',
			],
			'mentorid' => [
				'type' => 'hidden',
				'default' => $mentorUser->getId()
			]
		];
	}

	/**
	 * @param array $data
	 * @return bool|Status
	 */
	public function onRemoveMentorSubmit( array $data ) {
		// ensure the user has the necessary permissions to do this
		if ( !$this->canManageMentors() ) {
			return false;
		}

		$mentorUser = $this->userIdentityLookup->getUserIdentityByUserId( (int)$data['mentorid'] );
		if ( !$mentorUser ) {
			return Status::newFatal( 'growthexperiments-manage-mentors-error-no-such-user' );
		}

		return Status::wrap(
			$this->mentorWriter->removeMentor(
				$this->mentorProvider->newMentorFromUserIdentity( $mentorUser ),
				$this->getUser(),
				$data['reason']
			)
		);
	}

	/**
	 * Handle user clicking at Remove button
	 *
	 * @param string|null $par
	 * @return bool
	 */
	private function handleRemoveMentor( ?string $par ) {
		if ( !$par || strpos( $par, 'remove-mentor/' ) !== 0 ) {
			return false;
		}

		if ( !$this->canManageMentors() ) {
			return false;
		}

		$mentorId = (int)explode( '/', $par )[1];
		if ( !$mentorId ) {
			return false;
		}

		$out = $this->getOutput();
		$mentorUser = $this->userIdentityLookup->getUserIdentityByUserId( $mentorId );
		if ( !$mentorUser ) {
			$out->addHTML( Html::element(
				'p',
				[ 'class' => 'error' ],
				$this->msg(
					'growthexperiments-manage-mentors-error-no-such-user',
					$mentorId
				)->text()
			) );
			return true;
		}

		$form = HTMLForm::factory(
			'ooui',
			$this->getManageMentorsFormFields( $mentorUser ),
			$this->getContext(),
			'growthexperiments-manage-mentors-'
		);
		if ( $this->getRequest()->getMethod() == 'GET' ) {
			$out->addWikiMsg(
				'growthexperiments-manage-mentors-remove-mentor-pretext',
				$mentorUser->getName()
			);
		}
		$form->setSubmitCallback( [ $this, 'onRemoveMentorSubmit' ] );

		if ( $form->show() ) {
			$out->addWikiMsg(
				'growthexperiments-manage-mentors-remove-mentor-success',
				$mentorUser->getName()
			);
			$out->addWikiMsg(
				'growthexperiments-manage-mentors-return-back'
			);
		}

		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function execute( $subPage ) {
		parent::execute( $subPage );
		if ( $this->handleRemoveMentor( $subPage ) ) {
			return;
		}

		$out = $this->getOutput();
		$out->enableOOUI();
		$out->addHTML( implode( "\n", [
			Html::element( 'h2', [], $this->msg( 'growthexperiments-manage-mentors-auto-assigned' )->text() ),
			$this->getMentorsTable( $this->mentorProvider->getAutoAssignedMentors() ),
			Html::element( 'h2', [], $this->msg( 'growthexperiments-manage-mentors-manually-assigned' )->text() ),
			$this->getMentorsTable( $this->mentorProvider->getManuallyAssignedMentors() ),
		] ) );
	}
}
