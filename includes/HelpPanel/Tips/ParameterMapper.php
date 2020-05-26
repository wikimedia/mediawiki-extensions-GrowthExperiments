<?php

namespace GrowthExperiments\HelpPanel\Tips;

use Closure;
use IContextSource;
use MessageLocalizer;
use OOUI\IconWidget;

class ParameterMapper {

	/**
	 * @var TipInterface
	 */
	private $tip;
	/**
	 * @var IContextSource
	 */
	private $messageLocalizer;
	/**
	 * @var string
	 */
	private $tipMessageKey;
	/**
	 * @var string
	 */
	private $skinName;

	/**
	 * @param MessageLocalizer $messageLocalizer
	 * @param string $skinName
	 */
	public function __construct( MessageLocalizer $messageLocalizer, string $skinName ) {
		$this->messageLocalizer = $messageLocalizer;
		$this->skinName = $skinName;
	}

	/**
	 * @param TipInterface $tip
	 * @param string $step
	 * @return TipRenderParameters
	 */
	public function getParameters( TipInterface $tip, string $step ) :TipRenderParameters {
		$this->tip = $tip;
		$this->tipMessageKey = $tip->getConfig()->getMessageKey();
		$taskTypeId = $tip->getConfig()->getTaskTypeId();
		$tipTypeId = $tip->getConfig()->getTipTypeId();
		$callbacks = $this->getMap()[$taskTypeId][$step][$tipTypeId] ?? [];
		return $this->getRenderParametersFromCallbacks( $callbacks );
	}

	/**
	 * Get a TipRenderParameters instance from an array of closures.
	 *
	 * The closures may return mixed values (User object, plain-text messages,
	 * image src, links) for use in rendering a tip, or an instance of
	 * TipRenderParameters directly if the closure wants to override the message
	 * key.
	 * @param Closure[] $callbacks
	 * @return TipRenderParameters
	 */
	private function getRenderParametersFromCallbacks( $callbacks ) :TipRenderParameters {
		$parameters = [];
		$messageKey = $this->tipMessageKey;
		foreach ( $callbacks as $callback ) {
			$renderParams = $callback();
			// If the callback returns an instance of TipRenderParameters,
			// merge its message key and extra parameters into the values
			// passed to TipRenderParameters.
			// This is done in case a callback needs to have control over the
			// message key, as is the case for 'getLearnMoreTitleCallback'
			if ( $renderParams instanceof TipRenderParameters ) {
				$parameters += $renderParams->getExtraParameters();
				if ( $this->tipMessageKey !== $renderParams->getMessageKey() ) {
					$messageKey = $renderParams->getMessageKey();
				}
			} else {
				$parameters[] = $renderParams;
			}
		}
		return new TipRenderParameters( $messageKey, $parameters );
	}

	/**
	 * Return a mapping of task type => tip set number => tip type => callbacks.
	 *
	 * Each callback can return mixed values that are passed to the tip's render
	 * function. In most cases the values are passed to the Message class as
	 * parameters, but in other cases (e.g. Graphic) the parameters are used
	 * as values for generating the HTML for an <img> tag.
	 *
	 * The callback may also return an instance of TipRenderParameters if it
	 * wishes to override the message key, e.g. in the case of choosing an
	 * alternate message key without a link if the required configuration for
	 * rendering a link is not present.
	 * @return array[]
	 */
	private function getMap() :array {
		return [
			'copyedit' => [
				'calm' => [
					'graphic' => [ $this->getImgSrcCallback( 'intro-typo', '.svg' ) ]
				],
				'step1' => [
					'main' => [ $this->getViewEditMessageCallback() ]
				],
				'publish' => [
					'main' => [ $this->getMessageCallback( 'publishchanges-start' ) ],
					'text' => [
						$this->getLearnMoreTitleCallback( false, true )
					]
				],
			],
			'links' => [
				'step1' => [
					'main' => [ $this->getViewEditMessageCallback() ],
				],
				'step2' => [
					'main' => [
						$this->getMessageCallback( 'visualeditor-annotationbutton-link-tooltip' ),
						$this->getIconCallback( 'link' )
					],
				],
				'publish' => [
					'main' => [
						$this->getMessageCallback( 'publishchanges-start' )
					],
					'text' => [ $this->getLearnMoreTitleCallback( false, true ) ]
				]
			],
			'update' => [
				'step1' => [
					'main' => [
						$this->getViewEditMessageCallback()
					],
				],
				'step2' => [
					'main' => [
						$this->getLearnMoreTitleCallback( true )
					],
				],
				'publish' => [
					'main' => [
						$this->getMessageCallback( 'publishchanges-start' )
					],
					'text' => [ $this->getLearnMoreTitleCallback( false, true ) ]
				],
			],
			'expand' => [
				'step1' => [
					'main' => [
						$this->getViewEditMessageCallback()
					],
				],
				'step2' => [
					'main' => [
						$this->getLearnMoreTitleCallback( true )
					],
				],
				'publish' => [
					'main' => [
						$this->getMessageCallback( 'publishchanges-start' )
					],
					'text' => [ $this->getLearnMoreTitleCallback( false, true ) ]
				],
			],
			'references' => [
				'step1' => [
					'main' => [
						$this->getViewEditMessageCallback()
					]
				],
				'step2' => [
					'main' => [
						$this->getIconCallback( 'browser' ),
						$this->getIconCallback( 'book' ),
						$this->getIconCallback( 'journal' ),
						$this->getIconCallback( 'reference' ),
					]
				],
				'step3' => [
					'main' => [
						$this->getMessageCallback( 'cite-ve-toolbar-group-label' ),
						$this->getIconCallback( 'quotes', 'cite-ve-toolbar-group-label', true )
					],
				],
				'publish' => [
					'main' => [
						$this->getMessageCallback( 'publishchanges-start' )
					],
					'text' => [ $this->getLearnMoreTitleCallback( false, true ) ]
				],
			]
		];
	}

	/**
	 * @return Closure
	 */
	private function getViewEditMessageCallback() :Closure {
		return function () {
			return $this->messageLocalizer->msg(
				$this->skinName === 'vector' ? 'vector-view-edit' : 'mobile-frontend-editor-edit'
			)->plain();
		};
	}

	/**
	 * @param string $icon
	 * @param string $labelKey
	 * @param bool $framed
	 * @return Closure
	 */
	private function getIconCallback( string $icon, $labelKey = '', $framed = true ) :Closure {
		return function () use ( $icon, $labelKey, $framed ) {
			$config = [
				'icon' => $icon,
				'framed' => $framed
			];
			if ( $labelKey ) {
				$config['label'] = $this->messageLocalizer->msg( $labelKey )->plain();
			}
			return new IconWidget( $config );
		};
	}

	/**
	 * @param string $key
	 * @return Closure
	 */
	private function getMessageCallback( string $key ) :Closure {
		return function () use ( $key ) {
			return $this->messageLocalizer->msg( $key );
		};
	}

	/**
	 * @param bool $useFallbackVariant
	 * @param bool $throwIfNotSet
	 * @return Closure
	 */
	private function getLearnMoreTitleCallback(
		bool $useFallbackVariant = false, bool $throwIfNotSet = false
	) :Closure {
		return function () use ( $useFallbackVariant, $throwIfNotSet ) {
			$msgKey = $this->tip->getConfig()->getMessageKey();
			if ( $useFallbackVariant && !$this->tip->getConfig()->getLearnMoreTitle() ) {
				$msgKey .= '-no-link';
			} elseif ( $throwIfNotSet && !$this->tip->getConfig()->getLearnMoreTitle() ) {
				// NB this won't result in a user facing error; the exception is caught
				// and the end result is that this tip will not be rendered in the response.
				throw new TipRenderException( 'The learn more title is required to render this tip.' );
			}
			return new TipRenderParameters(
				$msgKey, [ $this->tip->getConfig()->getLearnMoreTitle() ]
			);
		};
	}

	/**
	 * @param string $filename
	 * @param string $suffix
	 * @return Closure
	 */
	private function getImgSrcCallback( string $filename, string $suffix ) :Closure {
		return function () use ( $filename, $suffix ) {
			return $this->tip->getConfig()->getExtraConfig()['ExtensionAssetsPath'] .
				'/GrowthExperiments/images/' . $filename . '-' .
				$this->messageLocalizer->getLanguage()->getDir() .
				$suffix;
		};
	}
}
