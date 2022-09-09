<?php

namespace GrowthExperiments\Specials\Forms;

use GrowthExperiments\Mentorship\Provider\IMentorWriter;
use GrowthExperiments\Mentorship\Provider\MentorProvider;
use IContextSource;
use MediaWiki\Permissions\Authority;
use MediaWiki\User\UserIdentity;
use OOUIHTMLForm;
use Status;

abstract class ManageMentorsAbstractForm extends OOUIHTMLForm {

	/** @var MentorProvider */
	protected $mentorProvider;

	/** @var IMentorWriter */
	protected $mentorWriter;

	/** @var UserIdentity */
	protected $mentorUser;

	/**
	 * @param MentorProvider $mentorProvider
	 * @param IMentorWriter $mentorWriter
	 * @param UserIdentity $mentorUser
	 * @param IContextSource $context
	 * @param string $messagePrefix
	 */
	public function __construct(
		MentorProvider $mentorProvider,
		IMentorWriter $mentorWriter,
		UserIdentity $mentorUser,
		IContextSource $context,
		string $messagePrefix = ''
	) {
		// must happen before calling getFormFields(), as that might make use of $mentorUser
		$this->mentorProvider = $mentorProvider;
		$this->mentorWriter = $mentorWriter;
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
	 * @inheritDoc
	 */
	public function show() {
		$res = parent::show();
		if ( $res ) {
			$this->onSuccess();
		}
		return $res;
	}
}
