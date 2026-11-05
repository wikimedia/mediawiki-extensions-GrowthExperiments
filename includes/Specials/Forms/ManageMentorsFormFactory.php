<?php

namespace GrowthExperiments\Specials\Forms;

use MediaWiki\Context\IContextSource;
use MediaWiki\User\UserIdentity;
use Wikimedia\ObjectFactory\ObjectFactory;

class ManageMentorsFormFactory {

	public function __construct(
		private readonly ObjectFactory $objectFactory,
	) {
	}

	public function getFormByAction(
		string $action,
		?UserIdentity $mentorUser,
		IContextSource $context
	): ?ManageMentorsAbstractForm {
		$spec = match ( $action ) {
			'remove-mentor' => [
				'class' => ManageMentorsRemoveMentor::class,
				'services' => [
					'GrowthExperimentsMentorRemover',
				],
				'args' => [ $mentorUser, $context ],
			],
			'add-mentor' => [
				'class' => ManageMentorsAddMentor::class,
				'services' => [
					'UserIdentityLookup',
					'GrowthExperimentsMentorProvider',
					'GrowthExperimentsMentorWriter',
				],
				'args' => [ $context ],
			],
			'edit-mentor' => [
				'class' => ManageMentorsEditMentor::class,
				'services' => [
					'GrowthExperimentsMentorProvider',
					'GrowthExperimentsMentorWriter',
					'GrowthExperimentsMentorStatusManager',
				],
				'args' => [ $mentorUser, $context ],
			],
			default => null,
		};
		if ( $spec === null ) {
			return null;
		}

		return $this->objectFactory->createObject( $spec, [
			'assertClass' => ManageMentorsAbstractForm::class,
		] );
	}
}
