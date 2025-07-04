<?php

namespace GrowthExperiments\NewcomerTasks;

use MediaWiki\Extension\Notifications\Formatters\EchoEventPresentationModel;
use MediaWiki\Message\Message;
use MediaWiki\SpecialPage\SpecialPage;

class AddALinkMilestonePresentationModel extends EchoEventPresentationModel {

	/**
	 * @inheritDoc
	 */
	public function getIconType() {
		return 'growthexperiments-addalink-milestone';
	}

	public function getHeaderMessageKey(): string {
		return 'growthexperiments-addalink-milestone-notification-header';
	}

	public function getHeaderMessage(): Message {
		$threshold = $this->event->getExtraParam( 'threshold' );
		$viewsCount = $this->event->getExtraParam( 'views-count', 0 );
		$msg = $this->msg( $this->getHeaderMessageKey() );
		$msg->numParams( $threshold )
			->params( $this->getViewingUserForGender() )
			->numParams( $viewsCount );

		return $msg;
	}

	/**
	 * @inheritDoc
	 */
	public function getPrimaryLink() {
		return false;
	}

	/**
	 * @inheritDoc
	 */
	public function getSecondaryLinks() {
		return [ [
			'url' => $this->getSpecialHomepageUrl( 'get-started-primary-link-' . $this->getDistributionType() ),
			'label' => $this->msg(
				'growthexperiments-addalink-milestone-notification-call-to-action-label'
			)->text(),
			'icon' => 'edit',
		] ];
	}

	/**
	 * Get the URL for Special:Homepage with suggested edits fragment
	 *
	 * @param string $source Analytics source parameter
	 * @return string Full URL with fragment
	 */
	private function getSpecialHomepageUrl( string $source ): string {
		$title = SpecialPage::getTitleFor( 'Homepage' );
		$url = $title->getFullURL( [ 'source' => $source ] );

		return $url . '#/homepage/suggested-edits/openTaskTypeDialog';
	}
}
