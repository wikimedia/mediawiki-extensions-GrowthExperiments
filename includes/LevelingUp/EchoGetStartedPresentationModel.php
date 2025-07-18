<?php

namespace GrowthExperiments\LevelingUp;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\VariantHooks;
use MediaWiki\Extension\Notifications\Formatters\EchoEventPresentationModel;
use MediaWiki\MediaWikiServices;

/**
 * Echo notification for "Get Started" (T322435)
 */
class EchoGetStartedPresentationModel extends EchoEventPresentationModel {

	/** @inheritDoc */
	public function getIconType() {
		return 'growthexperiments-get-started';
	}

	/** @inheritDoc */
	public function getHeaderMessage() {
		// Echo presentation models do not support DI
		$experimentManager = GrowthExperimentsServices::wrap( MediaWikiServices::getInstance() )
			->getExperimentUserManager();

		if ( $experimentManager->isUserInVariant( $this->getUser(), VariantHooks::VARIANT_GET_STARTED_NOTIFICATION ) ) {
			return $this->getMessageWithAgent( 'growthexperiments-levelingup-getstarted-notification-header-new' );
		}
		return $this->getMessageWithAgent( 'growthexperiments-levelingup-getstarted-notification-header' );
	}

	/** @inheritDoc */
	public function getSecondaryLinks() {
		// We don't include the secondary link in email, because it's the same as the primary link,
		// and it looks awkward in an email to have two buttons leading to the same thing.
		if ( $this->getDistributionType() === 'email' ) {
			return [];
		}
		return [ [
			'url' => $this->getSpecialHomepageUrl( 'get-started-secondary-link' ),
			'label' => $this->msg( 'growthexperiments-levelingup-getstarted-notification-call-to-action-label' ),
			'icon' => 'edit'
		] ];
	}

	/** @inheritDoc */
	public function getPrimaryLink() {
		return [
			'url' => $this->getSpecialHomepageUrl( 'get-started-primary-link-' . $this->getDistributionType() ),
			'label' => $this->msg(
				'growthexperiments-levelingup-getstarted-notification-call-to-action-label'
			)->text(),
			'icon' => 'edit'
		];
	}

	private function getSpecialHomepageUrl( string $source ): string {
		$title = $this->event->getTitle();
		$title->setFragment( '#/homepage/suggested-edits' );
		return $title->getCanonicalURL( [ 'source' => $source ] );
	}
}
