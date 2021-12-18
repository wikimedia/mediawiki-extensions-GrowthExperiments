<?php

namespace GrowthExperiments\MentorDashboard\Modules;

use GrowthExperiments\Mentorship\MentorManager;
use Html;
use IContextSource;
use MalformedTitleException;
use MediaWiki\Linker\LinkRenderer;
use TitleParser;

class Resources extends BaseModule {
	/** @var TitleParser */
	private $titleParser;

	/** @var LinkRenderer */
	private $linkRenderer;

	/** @var MentorManager */
	private $mentorManager;

	/**
	 * @param string $name
	 * @param IContextSource $ctx
	 * @param TitleParser $titleParser
	 * @param LinkRenderer $linkRenderer
	 * @param MentorManager $mentorManager
	 */
	public function __construct(
		$name,
		IContextSource $ctx,
		TitleParser $titleParser,
		LinkRenderer $linkRenderer,
		MentorManager $mentorManager
	) {
		parent::__construct( $name, $ctx );

		$this->titleParser = $titleParser;
		$this->linkRenderer = $linkRenderer;
		$this->mentorManager = $mentorManager;
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
		$mentorsTitle = $this->mentorManager->getAutoMentorsListTitle();
		if ( $mentorsTitle ) {
			array_unshift( $links, $this->formatLink(
				$mentorsTitle->getPrefixedText(),
				$this->msg( 'growthexperiments-mentor-dashboard-resources-link-mentors-list' )->text()
			) );
		}
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
