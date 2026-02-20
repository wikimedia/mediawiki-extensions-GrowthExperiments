<?php

namespace GrowthExperiments\HelpPanel\Tips;

use LogicException;
use MediaWiki\Html\Html;
use MediaWiki\Output\OutputPage;
use MessageLocalizer;
use OOUI\IconWidget;

/**
 * Transform an array of TipNodes into an array of rendered HTML.
 */
class TipNodeRenderer {

	private MessageLocalizer $messageLocalizer;
	private string $extensionAssetsPath;

	public function __construct( string $extensionAssetsPath ) {
		$this->extensionAssetsPath = $extensionAssetsPath;
	}

	public function setMessageLocalizer( MessageLocalizer $messageLocalizer ) {
		$this->messageLocalizer = $messageLocalizer;
	}

	/**
	 * Render a set of tip nodes into HTML.
	 *
	 * This method is called recursively as the TipNode tree is rendered.
	 *
	 * @param TipNode[] $nodes
	 * @param string $skinName
	 * @param string $dir
	 * @return string[] An array of rendered HTML.
	 */
	public function render( array $nodes, string $skinName, string $dir ): array {
		OutputPage::setupOOUI( $skinName, $dir );
		return array_values( array_map( function ( $node ) use ( $skinName, $dir ) {
			return $this->buildHtml( $node, $skinName, $dir );
		}, $nodes ) );
	}

	private function buildHtml( TipNode $node, string $skin, string $dir ): string {
		switch ( $node->getType() ) {
			case 'header':
			case 'main':
			case 'main-multiple':
			case 'text':
				return $this->mainAndTextRender( $node, $skin );
			case 'graphic':
				return $this->graphicRender( $node, $dir );
			case 'example':
				return $this->exampleRender( $node );
			default:
				throw new LogicException( $node->getType() . 'is not a valid tip type ID.' );
		}
	}

	/**
	 * @param string $tipTypeId
	 * @param string[] $textVariants
	 * @return string[]
	 */
	private function getBaseCssClasses( string $tipTypeId, array $textVariants = [] ): array {
		return [
			'growthexperiments-quickstart-tips-tip',
			'growthexperiments-quickstart-tips-tip-' . $tipTypeId,
			...array_map(
				static fn ( $variant ) => 'growthexperiments-quickstart-tips-tip--' . $variant,
				$textVariants
			),
		];
	}

	private function mainAndTextRender( TipNode $node, string $skinName ): string {
		$tipTextVariants = array_values( array_map( static function ( $item ) {
			if ( $item['type'] == TipTree::TIP_DATA_TYPE_TEXT_VARIANT ) {
				return $item['data'];
			}
			return null;
		}, $node->getData() ) );

		return Html::rawElement( 'div', [
			'class' => $this->getBaseCssClasses( $node->getType(), $tipTextVariants ),
		], $this->messageLocalizer->msg(
			$this->getMessageKeyWithVariantFallback( $node ), $this->getMessageParameters( $node, $skinName )
		)->parse() );
	}

	/**
	 * Obtain a message key for use with Message.
	 *
	 * This is usually determined by TipLoader, which finds a i18n key based
	 * on the current editor, skin, task type and tip type. But this method
	 * allows for overriding with a variant in the event the TipNode specifies
	 * a title type but the value for that title is not present.
	 *
	 * @param TipNode $node
	 * @return string
	 */
	private function getMessageKeyWithVariantFallback( TipNode $node ): string {
		$messageKey = $node->getMessageKey();
		$messageKeyVariant = current( array_filter( array_map( static function ( $nodeConfig ) {
			// This could be more flexible, but as we don't have a use
			// case yet, leaving as is for now.
			if ( $nodeConfig['type'] === TipTree::TIP_DATA_TYPE_TITLE &&
				!$nodeConfig['data']['title'] ) {
				return $nodeConfig['data']['messageKeyVariant'] ?? [];
			}
			return [];
		}, $node->getData() ) ) );
		if ( $messageKeyVariant ) {
			$messageKey .= $messageKeyVariant;
		}
		return $messageKey;
	}

	private function graphicRender( TipNode $node, string $dir ): string {
		if ( !$node->getData()[0]['type'] || $node->getData()[0]['type'] !== 'image' ) {
			return '';
		}
		return Html::rawElement( 'img', [
			'class' => $this->getBaseCssClasses( $node->getType() ),
			'src' => $this->getImageSourcePath(
				$node->getData()[0]['data']['filename'],
				$node->getData()[0]['data']['suffix'],
				$dir
			),
			// Leaving alt blank per T245786#6115403; screen readers
			// should ignore this decorative image.
			'alt' => '',
		] );
	}

	private function getImageSourcePath( string $filename, string $suffix, string $dir ): string {
		return $this->extensionAssetsPath . '/GrowthExperiments/images/' .
			$filename . '-' . $dir . '.' . $suffix;
	}

	private function exampleRender( TipNode $node ): string {
		$exampleLabelKey = $node->getData()[0]['data']['labelKey'] ?? null;
		$exampleLabel = $exampleLabelKey ?
			Html::element( 'div',
			[ 'class' => 'growthexperiments-quickstart-tips-tip-example-label' ],
				$this->messageLocalizer->msg( $exampleLabelKey )->text() )
			: '';
		return $exampleLabel . Html::rawElement( 'div', [
			'class' => $this->getBaseCssClasses( $node->getType() ),
		],
			Html::rawElement( 'p', [
			'class' => [
				'growthexperiments-quickstart-tips-tip-' . $node->getType() . '-text',
			] ], $this->messageLocalizer->msg(
				$this->getMessageKeyWithVariantFallback( $node )
			)->parse() ) );
	}

	private function getMessageParameters( TipNode $node, string $skinName ): array {
		return array_filter( array_map( function ( $nodeConfig ) use ( $skinName ) {
			switch ( $nodeConfig['type'] ) {
				case TipTree::TIP_DATA_TYPE_PLAIN_MESSAGE:
					$parameterMessageKey = $nodeConfig['variant'][$skinName]['data'] ?? $nodeConfig['data'];
					return $this->messageLocalizer->msg( $parameterMessageKey )->plain();
				case TipTree::TIP_DATA_TYPE_OOUI_ICON:
					$iconConfig = [
						'icon' => $nodeConfig['data']['icon'],
						'framed' => $nodeConfig['data']['framed'] ?? true,
					];
					if ( isset( $nodeConfig['data']['labelKey'] ) ) {
						$iconConfig['label'] = $this->messageLocalizer->msg(
							$nodeConfig['data']['labelKey']
						)->plain();
					}
					return new IconWidget( $iconConfig );
				case TipTree::TIP_DATA_TYPE_TITLE:
					return $nodeConfig['data']['title'];
				case TipTree::TIP_DATA_TYPE_TEXT_VARIANT:
					return null;
				default:
					throw new LogicException( $nodeConfig['type'] . ' is not supported' );
			}
		}, $node->getData() ) );
	}

}
