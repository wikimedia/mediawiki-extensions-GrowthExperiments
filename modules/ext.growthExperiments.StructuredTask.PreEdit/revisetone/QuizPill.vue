<template>
	<div
		class="ext-growthExperiments-ReviseTone-QuizPill"
		@keyup.enter="onPillClick"
		@click="onPillClick"
	>
		<div
			class="ext-growthExperiments-ReviseTone-QuizPill-Pill"
			:class="classObject"
			:tabindex="reveal ? null : 0"
			:role="reveal ? null : 'button'"
		>
			<cdx-icon
				v-if="icon"
				size="medium"
				:icon="icon"
				:lang="lang"
			></cdx-icon>
			<number-icon
				v-if="Number.isInteger( iconNumber ) && !icon"
				:number="iconNumber"
			></number-icon>
			<div class="ext-growthExperiments-ReviseTone-QuizPill-Pill__content">
				{{ renderedLabel }}
			</div>
		</div>
		<div
			v-if="computedStatus === 'correct' && description"
			v-i18n-html="description"
			class="ext-growthExperiments-ReviseTone-QuizPill-Description"
		>
		</div>
	</div>
</template>

<script>
const { defineComponent, computed, inject } = require( 'vue' );
const { CdxIcon } = require( '@wikimedia/codex' );
const { cdxIconSuccess, cdxIconClear } = require( '../common/codex-icons.json' );
const NumberIcon = require( './NumberIcon.vue' );
const PILL_TO_CHIP_STATUS_MAP = {
	interactable: 'notice',
	'non-interactable': 'notice',
	correct: 'success',
	incorrect: 'error',
};
const PILL_TO_CHIP_ICON_MAP = {
	interactable: null,
	'non-interactable': null,
	correct: cdxIconSuccess,
	incorrect: cdxIconClear,
};

// @vue/component
module.exports = exports = defineComponent( {
	name: 'QuizPill',
	components: {
		NumberIcon,
		CdxIcon,
	},
	props: {
		label: {
			type: String,
			required: true,
		},
		reveal: {
			type: [ String, null ],
			default: null,
		},
		correct: {
			type: Boolean,
			default: false,
		},
		iconNumber: {
			type: Number,
			default: null,
		},
		description: {
			type: String,
			default: '',
		},
	},
	emits: [ 'click' ],
	setup( props, ctx ) {
		const { getFallbackLanguageChain } = inject( 'mw.language' );
		const computedStatus = computed( () => {
			let status = 'non-interactable';
			if ( !props.reveal ) {
				return 'interactable';
			}
			// The user has answered
			if ( props.label === props.reveal ) {
				// This is the option the user answered
				if ( props.correct ) {
					status = 'correct';
				} else {
					status = 'incorrect';
				}
			}
			// Always show the right answer regardless of the user answer
			if ( props.correct ) {
				status = 'correct';
			}
			return status;
		} );
		const pillStatus = computed( () => PILL_TO_CHIP_STATUS_MAP[ computedStatus.value ] );
		const icon = computed( () => PILL_TO_CHIP_ICON_MAP[ computedStatus.value ] );
		const lang = getFallbackLanguageChain().shift();
		const onPillClick = () => {
			if ( props.reveal ) {
				return;
			}
			ctx.emit( 'click' );
		};
		const pillStatusCls = computed( () => `ext-growthExperiments-ReviseTone-QuizPill-Pill--${ pillStatus.value }` );
		const classObject = computed( () => ( {
			[ pillStatusCls.value ]: true,
			'ext-growthExperiments-ReviseTone-QuizPill-Pill--not-interactable': !!props.reveal,
		} ) );
		const i18n = inject( 'i18n' );
		const renderedLabel = i18n( props.label ).text();
		return {
			classObject,
			computedStatus,
			icon,
			lang,
			onPillClick,
			renderedLabel,
		};
	},
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.ext-growthExperiments-ReviseTone-QuizPill {
  margin: 0;
  width: @size-full;

  &-Pill {
    display: flex;
    align-items: center;
    padding-left: @size-75;
    padding-top: @size-75;
    padding-bottom: @size-75;
    gap: @size-50;
    width: auto;
    min-width: auto;
    border-radius: @border-radius-pill;
    border: @border-width-base solid @border-color-interactive;
    background-color: @background-color-base;

    &:focus:not( :active ):not( .ext-growthExperiments-ReviseTone-QuizPill-Pill--not-interactable ) {
      outline: @outline-base--focus;
      border-color: @border-color-progressive--focus;
      box-shadow: @box-shadow-inset-small @box-shadow-color-progressive--focus;
    }

    &--notice:not( .ext-growthExperiments-ReviseTone-QuizPill-Pill--not-interactable ) {
      cursor: pointer;

      &:hover {
        background-color: @background-color-interactive;
      }
    }

    .cdx-icon {
      color: inherit;
    }

    &--success {
      color: @color-success;
      border-color: @border-color-success;
      background-color: @background-color-success-subtle;
    }

    &--error {
      color: @color-error;
      border-color: @border-color-error;
      background-color: @background-color-error-subtle;
    }

    &__content {
      overflow: hidden;
      display: -webkit-box;
      line-clamp: 1;
      -webkit-line-clamp: 1;
      -webkit-box-orient: vertical;
    }
  }

  &-Description {
    width: auto;
    margin-top: @size-25;
    color: @color-success;
    font-size: @font-size-small;
    font-weight: @font-weight-bold;
    line-height: @line-height-x-small;

    .cdx-learn-more-link {
      .cdx-mixin-link();
    }
  }
}
</style>
`
