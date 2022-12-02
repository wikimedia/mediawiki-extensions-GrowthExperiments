<?php

namespace GrowthExperiments\MentorDashboard\Modules;

use Html;
use SpecialPage;

class MenteeOverview extends BaseModule {

	/** @var string Option name to store user presets. This is client-side hardcoded. */
	public const PRESETS_PREF = 'growthexperiments-mentee-overview-presets';

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
		return $this->msg( 'growthexperiments-mentor-dashboard-mentee-overview-intro' )->text();
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
				'class' => 'growthexperiments-mentor-dashboard-module-mentee-overview-container'
			],
			implode( "\n", [
				$this->getClientSideBody(),
				$this->getRecentEditsByMenteesBody()
			] )
		);
	}

	/**
	 * Get skeleton body to be replaced on the client side
	 *
	 * Should only have a no-js-fallback in it, to display meaningful
	 * information for no-JS clients.
	 *
	 *
	 * @return string
	 */
	protected function getClientSideBody(): string {
		return Html::rawElement(
			'div',
			[
				'id' => 'vue-root',
				'class' => 'growthexperiments-mentor-dashboard-module-mentee-overview-content'
			],
			Html::element(
				'p',
				[ 'class' => 'growthexperiments-mentor-dashboard-no-js-fallback' ],
				$this->msg( 'growthexperiments-mentor-dashboard-mentee-overview-no-js-fallback' )->text()
			)
		);
	}

	/**
	 * @return string
	 */
	private function getRecentEditsByMenteesBody(): string {
		return Html::rawElement(
			'div',
			[
				'class' => 'growthexperiments-mentor-dashboard-module-mentee-overview-recent-by-mentees'
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
				)
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
