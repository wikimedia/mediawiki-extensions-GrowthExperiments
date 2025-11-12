<?php

namespace GrowthExperiments\MentorDashboard\Modules;

use GrowthExperiments\MentorDashboard\MenteeOverview\MenteeOverviewDataUpdater;
use GrowthExperiments\Util;
use MediaWiki\Context\IContextSource;
use MediaWiki\Html\Html;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\User\UserOptionsLookup;

class MenteeOverview extends BaseModule {

	/** @var string Option name to store user presets. This is client-side hardcoded. */
	public const PRESETS_PREF = 'growthexperiments-mentee-overview-presets';

	public function __construct( IContextSource $ctx, private readonly UserOptionsLookup $userOptions ) {
		parent::__construct( 'mentee-overview', $ctx );
	}

	/**
	 * @inheritDoc
	 */
	protected function getHeaderText() {
		return $this->msg( 'growthexperiments-mentor-dashboard-mentee-overview-headline' )->text();
	}

	/**
	 * @inheritDoc
	 */
	protected function getSubheaderText() {
		$user = $this->getContext()->getUser();
		$lastUpdate = $this->userOptions->getOption( $user, MenteeOverviewDataUpdater::LAST_UPDATE_PREFERENCE );
		$lastUpdateTimeStamp = wfTimestamp( TS_UNIX, $lastUpdate );

		if ( $lastUpdateTimeStamp ) {
			$elapsedTime = (int)wfTimestamp() - (int)$lastUpdateTimeStamp;
			if ( $elapsedTime > 0 ) {
				$timeSinceLastUpdate = Util::getRelativeTime( $this->getContext(), $elapsedTime );
				return $this->msg( 'growthexperiments-mentor-dashboard-mentee-overview-info-updated',
					$timeSinceLastUpdate )->text();
			}
		}
		return '';
	}

	/**
	 * @inheritDoc
	 */
	protected function getSubheaderTag() {
		return 'p';
	}

	/**
	 * @inheritDoc
	 */
	protected function getBody() {
		return Html::rawElement(
			'div',
			[
				'class' => 'growthexperiments-mentor-dashboard-module-mentee-overview-container',
			],
			implode( "\n", [
				$this->getClientSideBody(),
				$this->getRecentEditsByMenteesBody(),
			] )
		);
	}

	/**
	 * Get skeleton body to be replaced on the client side
	 *
	 * Should only have a no-js-fallback in it, to display meaningful
	 * information for no-JS clients.
	 */
	protected function getClientSideBody(): string {
		return Html::rawElement(
			'div',
			[
				'id' => 'vue-root',
				'class' => 'growthexperiments-mentor-dashboard-module-mentee-overview-content',
			],
			Html::element(
				'p',
				[ 'class' => 'growthexperiments-mentor-dashboard-no-js-fallback' ],
				$this->msg( 'growthexperiments-mentor-dashboard-mentee-overview-no-js-fallback' )->text()
			)
		);
	}

	private function getRecentEditsByMenteesBody(): string {
		return Html::rawElement(
			'div',
			[
				'class' => 'growthexperiments-mentor-dashboard-module-mentee-overview-recent-by-mentees',
			],
			implode( "\n", [
				Html::element(
					'h4',
					[],
					$this->msg( 'growthexperiments-mentor-dashboard-mentee-overview-recent-edits-headline' )
						->text()
				),
				Html::rawElement(
					'p',
					[],
					$this->msg( 'growthexperiments-mentor-dashboard-mentee-overview-recent-edits-text' )
						->params( SpecialPage::getTitleFor( 'Recentchanges' )->getFullURL( [
							'mentorship' => 'all',
						] ) )
						->parse()
				),
			] )
		);
	}

	/**
	 * @inheritDoc
	 */
	protected function getMobileSummaryBody() {
		return $this->getBody();
	}
}
