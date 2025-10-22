<template>
	<div class="ext-growthExperiments-QuizGame">
		<div class="ext-growthExperiments-QuizGame__introduction">
			<i>{{ $i18n( 'growthexperiments-revisetone-quiz-game-example-title-label' ).text() }}</i>
			<div class="ext-growthExperiments-QuizGame__introduction__example">
				{{ data.example }}
			</div>
		</div>
		<div class="ext-growthExperiments-QuizGame__pills">
			<span class="ext-growthExperiments-QuizGame__pills__question">
				{{ $i18n( 'growthexperiments-revisetone-quiz-game-prompt-label' ).text() }}
			</span>
			<quiz-pill
				v-for="( option, index ) in quizOptions"
				:key="option.label"
				:reveal="wrappedResult"
				:icon-number="index + 1"
				v-bind="option"
				@click="onPillClick( option.label )"
			></quiz-pill>
		</div>
	</div>
</template>

<script>
const { defineComponent, toRef } = require( 'vue' );
const { useModelWrapper } = require( '@wikimedia/codex' );
const QuizPill = require( './QuizPill.vue' );
// @vue/component
module.exports = exports = defineComponent( {
	name: 'QuizGame',
	components: {
		QuizPill,
	},
	props: {
		data: {
			type: Object,
			required: true,
			default: () => ( { options: [] } ),
		},
		// eslint does not like the toRef syntax
		// eslint-disable-next-line vue/no-unused-properties
		result: {
			type: [ String, null ],
			default: null,
		},
	},
	setup( props, { emit } ) {
		const wrappedResult = useModelWrapper( toRef( props, 'result' ), emit, 'update:result' );
		const onPillClick = ( optionLabel ) => {
			wrappedResult.value = optionLabel;
		};

		return {
			quizOptions: props.data.options,
			onPillClick,
			wrappedResult,
		};
	},
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.ext-growthExperiments-QuizGame {
  width: 100%;
  background-color: @background-color-base;

  &__introduction {
    background-color: @background-color-neutral-subtle;
    padding: @spacing-100 @spacing-200;

    i,
 .example {
      font-size: @font-size-medium;
      line-height: @line-height-medium;
    }

    i {
      color: @color-subtle;
    }
  }

  &__pills {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: @spacing-100;
    gap: @spacing-75;
  }
}
</style>
