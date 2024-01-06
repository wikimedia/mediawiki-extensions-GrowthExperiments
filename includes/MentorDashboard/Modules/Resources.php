<?php

namespace GrowthExperiments\MentorDashboard\Modules;

use GrowthExperiments\Mentorship\Provider\MentorProvider;
use IContextSource;
use MediaWiki\Html\Html;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\MalformedTitleException;
use MediaWiki\Title\TitleParser;

class Resources extends BaseModule {
	/** @var TitleParser */
	private $titleParser;

	/** @var LinkRenderer */
	private $linkRenderer;

	/** @var MentorProvider */
	private $mentorProvider;

	/**
	 * @param string $name
	 * @param IContextSource $ctx
	 * @param TitleParser $titleParser
	 * @param LinkRenderer $linkRenderer
	 * @param MentorProvider $mentorProvider
	 */
	public function __construct(
		$name,
		IContextSource $ctx,
		TitleParser $titleParser,
		LinkRenderer $linkRenderer,
		MentorProvider $mentorProvider
	) {
		parent::__construct( $name, $ctx );

		$this->titleParser = $titleParser;
		$this->linkRenderer = $linkRenderer;
		$this->mentorProvider = $mentorProvider;
	}

	/**
	 * @inheritDoc
	 */
	protected function getHeaderText() {
		return $this->msg( 'growthexperiments-mentor-dashboard-resources-headline' )->text();
	}

	/**
	 * @inheritDoc
	 */
	protected function getHeaderIconName() {
		return 'references';
	}

	/**
	 * @inheritDoc
	 */
	protected function shouldHeaderIncludeIcon(): bool {
		return true;
	}

	/**
	 * @inheritDoc
	 */
	protected function getBody() {
		$links = [
			// TODO: For now, links are hardcoded here. In the full version of the
			// resources module, they should be customizable by the wiki (via interface
			// messages).
			$this->formatLink(
				SpecialPage::getTitleFor( 'ManageMentors' )->getPrefixedText(),
				$this->getUser()->isAllowed( 'managementors' ) ?
					$this->msg( 'growthexperiments-mentor-dashboard-resources-link-manage-mentors' )->text()
					: $this->msg( 'growthexperiments-mentor-dashboard-resources-link-view-mentor-list' )->text()
			),
			$this->formatLink(
				'mw:Special:MyLanguage/Growth/Communities/How to configure the mentors\' list',
				$this->msg( 'growthexperiments-mentor-dashboard-resources-link-how-to-introduce' )->text()
			),
			$this->formatLink(
				'mw:Special:MyLanguage/Help:Growth/Tools/How to claim a mentee',
				$this->msg( 'growthexperiments-mentor-dashboard-resources-link-claim-mentee' )->text()
			),
			$this->formatLink(
				'mw:Special:MyLanguage/Growth',
				$this->msg( 'growthexperiments-mentor-dashboard-resources-link-tools' )->text()
			)
		];

		return Html::rawElement(
			'ul',
			[],
			implode( "\n", array_filter( $links ) )
		);
	}

	/**
	 * @param string $targetText Text target for the link (parsed by TitleParser)
	 * @param string $text Description for the link
	 * @return ?string Null on error
	 */
	private function formatLink( string $targetText, string $text ): ?string {
		try {
			$target = $this->titleParser->parseTitle( $targetText );
		} catch ( MalformedTitleException $e ) {
			return null;
		}

		return Html::rawElement(
			'li',
			[],
			$this->linkRenderer->makeLink(
				$target,
				$text
			)
		);
	}

	/**
	 * @inheritDoc
	 */
	protected function getMobileSummaryBody() {
		return '';
	}
}
