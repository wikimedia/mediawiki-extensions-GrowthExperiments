<?php

namespace GrowthExperiments\HomepageModules;

use GrowthExperiments\AbstractExperimentManager;
use GrowthExperiments\Mentorship\IMentorManager;
use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;
use MediaWiki\Html\Html;
use OOUI\ButtonWidget;

class MentorshipOptIn extends BaseModule {

	/** @var IMentorManager */
	private $mentorManager;

	/**
	 * @param IContextSource $context
	 * @param Config $wikiConfig
	 * @param AbstractExperimentManager $experimentUserManager
	 * @param IMentorManager $mentorManager
	 */
	public function __construct(
		IContextSource $context,
		Config $wikiConfig,
		AbstractExperimentManager $experimentUserManager,
		IMentorManager $mentorManager
	) {
		parent::__construct( 'mentorship-optin', $context, $wikiConfig, $experimentUserManager );

		$this->mentorManager = $mentorManager;
	}

	/**
	 * @inheritDoc
	 */
	protected function canRender() {
		return $this->mentorManager->getMentorshipStateForUser(
			$this->getUser()
		) === IMentorManager::MENTORSHIP_OPTED_OUT;
	}

	/**
	 * @inheritDoc
	 */
	protected function getHeaderText() {
		return $this->msg( 'growthexperiments-homepage-mentorship-optin-header' )
			->text();
	}

	/**
	 * @inheritDoc
	 */
	protected function getHeaderIconName() {
		return 'userTalk';
	}

	/**
	 * @inheritDoc
	 */
	protected function getModules() {
		return $this->getMode() !== self::RENDER_MOBILE_SUMMARY ?
			[
				'ext.growthExperiments.Homepage.Mentorship',
			] : [];
	}

	/**
	 * @inheritDoc
	 */
	protected function getBody() {
		return implode( "\n", [
			$this->getIntroductionElement(),
			$this->getOptInButton(),
		] );
	}

	/**
	 * @inheritDoc
	 */
	protected function getMobileSummaryBody() {
		return Html::element(
			'div',
			[ 'class' => 'growthexperiments-homepage-module-text-light' ],
			$this->msg( 'growthexperiments-homepage-mentorship-optin-text' )->text()
		) . $this->getOptInButton();
	}

	private function getIntroductionElement(): string {
		return Html::element(
			'p',
			[],
			$this->msg( 'growthexperiments-homepage-mentorship-optin-text' )
				->text()
		);
	}

	/** @inheritDoc */
	public function shouldWrapModuleWithLink(): bool {
		return false;
	}

	private function getOptInButton(): string {
		return new ButtonWidget( [
			'id' => 'mw-ge-homepage-mentorship-optin',
			'framed' => false,
			'flags' => [ 'progressive' ],
			'icon' => 'mentor',
			'label' => $this->msg( 'growthexperiments-homepage-mentorship-optin-button' )
				->text(),
			'infusable' => true,
		] );
	}
}
