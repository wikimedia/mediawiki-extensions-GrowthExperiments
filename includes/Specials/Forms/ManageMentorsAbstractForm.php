<?php

namespace GrowthExperiments\Specials\Forms;

use IContextSource;
use MediaWiki\Permissions\Authority;
use MediaWiki\User\UserIdentity;
use OOUIHTMLForm;
use SpecialPage;
use Status;

abstract class ManageMentorsAbstractForm extends OOUIHTMLForm {

	/** @var UserIdentity */
	protected UserIdentity $mentorUser;

	/**
	 * @param UserIdentity $mentorUser
	 * @param IContextSource $context
	 * @param string $messagePrefix
	 */
	public function __construct(
		UserIdentity $mentorUser,
		IContextSource $context,
		string $messagePrefix = ''
	) {
		// must happen before calling getFormFields(), as that might make use of $mentorUser
		$this->mentorUser = $mentorUser;

		parent::__construct(
			$this->getFormFields(),
			$context,
			$messagePrefix
		);

		$this->setSubmitCallback( [ $this, 'onSubmit' ] );
	}

	/**
	 * Can $performer manage mentors?
	 *
	 * @param Authority $performer
	 * @return bool
	 */
	public static function canManageMentors( Authority $performer ): bool {
		return $performer->isAllowed( 'managementors' );
	}

	/**
	 * Get an HTMLForm descriptor array
	 *
	 * @see HTMLForm's class documentation for syntax.
	 * @return array
	 */
	abstract protected function getFormFields(): array;

	/**
	 * Process the form on POST submission.
	 *
	 * Must check canManageMentors() or otherwise assert the user
	 * is authorized to change mentorship-related properties.
	 *
	 * @param array $data
	 * @return bool|Status
	 */
	abstract public function onSubmit( array $data );

	/**
	 * Do something on successful processing of the form
	 *
	 * Useful to display a success message.
	 *
	 * @return void
	 */
	abstract protected function onSuccess(): void;

	/**
	 * Alter the form
	 *
	 * Form is available as $this. Method is called from show()
	 */
	protected function alterForm(): void {
		$this->getOutput()->addBacklinkSubtitle(
			SpecialPage::getTitleFor( 'ManageMentors' )
		);
	}

	/**
	 * @inheritDoc
	 */
	public function show() {
		$this->alterForm();

		$res = parent::show();
		if ( $res ) {
			$this->onSuccess();
		}
		return $res;
	}
}
