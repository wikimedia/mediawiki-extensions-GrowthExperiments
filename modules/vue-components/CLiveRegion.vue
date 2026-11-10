<template>
	<span class="ext-growthExperiments-CLiveRegion">
		<span
			v-if="visible"
			class="ext-growthExperiments-CLiveRegion__text"
			aria-hidden="true"
		>{{ visibleText }}</span>
		<span
			class="ext-growthExperiments-CLiveRegion__announcer"
			v-bind="attrs"
		>{{ announcement }}</span>
	</span>
</template>

<script>
// Temporary copy. Replace it with the Codex version of CLiveRegion when it is available.
// Do not use MediaWiki APIs in this file, so that it moves to Codex without changes.
const { computed } = require( 'vue' );
const useLiveRegion = require( './useLiveRegion.js' );

/**
 * A status message that screen readers announce when it changes.
 *
 * Always render this component, also while the message is empty. Screen readers do not
 * announce a change to a live region that they did not find before the change.
 *
 * By default, the message is visually hidden. With `visible`, the message is shown too. The
 * shown text and the announced text are separate elements: the shown text updates immediately
 * and screen readers ignore it, and the announced text is visually hidden and debounced. The
 * shown text always takes at least one line, so that the layout does not move when the
 * message changes between empty and set.
 *
 * The component is inline, and its content is in `<span>` elements. Thus, it can go in a
 * `<p>`, for example the `footer-text` slot of CdxDialog, and other text can follow the
 * message on the same line.
 */
// @vue/component
module.exports = exports = {
	name: 'CLiveRegion',
	compilerOptions: { whitespace: 'condense' },
	props: {
		/**
		 * The message to announce when it changes. An empty string clears the region.
		 */
		message: {
			type: String,
			default: '',
		},
		/**
		 * Whether to show the message, in addition to announcing it.
		 */
		visible: {
			type: Boolean,
			default: false,
		},
		/**
		 * How urgently screen readers announce the message: 'polite' or 'assertive'.
		 */
		politeness: {
			type: String,
			default: 'polite',
			validator: ( value ) => [ 'polite', 'assertive' ].includes( value ),
		},
		/**
		 * Time in milliseconds without changes before a message is announced.
		 */
		debounce: {
			type: Number,
			default: 500,
		},
		/**
		 * Maximum time in milliseconds that a message waits while changes continue.
		 */
		maxWait: {
			type: Number,
			default: 2000,
		},
	},
	expose: [ 'announce', 'clear' ],
	setup( props ) {
		const { announcement, attrs, announce, clear } = useLiveRegion(
			() => props.message,
			{
				politeness: () => props.politeness,
				debounce: () => props.debounce,
				maxWait: () => props.maxWait,
			},
		);

		// A no-break space keeps one line of height while the message is empty.
		const visibleText = computed( () => props.message || '\u00a0' );

		return {
			announcement,
			attrs,
			visibleText,
			/**
			 * Announce a message, also when it is the same as the last message.
			 *
			 * @param {string} text
			 */
			announce,
			/**
			 * Remove the message from the region, and discard the messages that wait.
			 */
			clear,
		};
	},
};
</script>

<style lang="less">
.ext-growthExperiments-CLiveRegion {
	// Keep the absolutely positioned announcer next to the component, so that it does not
	// change the scroll size of an ancestor.
	position: relative;

	&__announcer {
		display: block;
		position: absolute !important; /* stylelint-disable-line declaration-no-important */
		clip: rect( 1px, 1px, 1px, 1px );
		width: 1px;
		height: 1px;
		margin: -1px;
		border: 0;
		padding: 0;
		overflow: hidden;
	}
}
</style>
