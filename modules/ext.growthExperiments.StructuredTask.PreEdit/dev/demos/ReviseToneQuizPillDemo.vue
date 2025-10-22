<template>
	<div class="ReviseToneQuizPillDemo">
		<h1>Quiz pill demo</h1>
		<cdx-button
			:disabled="!selectedValue"
			@click="resetDemo">
			Reset demo
		</cdx-button>
		<div class="ReviseToneQuizPillDemo--pills">
			<h2>Which word needs revision?</h2>
			<quiz-pill
				v-for="( option, index ) in quizOptions"
				:key="option.label"
				:reveal="selectedValue"
				:icon-number="index + 1"
				v-bind="option"
				@click="onPillClick( option.label )"
			></quiz-pill>
		</div>
	</div>
</template>

<script>
const { defineComponent, ref } = require( 'vue' );
const { CdxButton } = require( '@wikimedia/codex' );
const QuizPill = require( '../../revisetone/QuizPill.vue' );
const QUIZ_DATA = [
	{
		label: 'born',
	},
	{
		label: 'beautiful',
		correct: true,
		description: 'This is a peacock term that promotes rather than informs readers.',
		link: {
			label: 'Learn more',
			href: '#',
		},
	},
	{
		label: 'city',
	},
	{
		label: 'None (looks good as it is) but has a very long context that may even flow into three consecutive lines',
	},
];

// @vue/component
module.exports = defineComponent( {
	name: 'ReviseToneQuizPillDemo',
	components: {
		CdxButton,
		QuizPill,
	},
	setup() {
		const selectedValue = ref( null );
		const resetDemo = () => {
			selectedValue.value = null;
		};
		const onPillClick = ( optionLabel ) => {
			selectedValue.value = optionLabel;
		};
		return {
			quizOptions: QUIZ_DATA,
			resetDemo,
			selectedValue,
			onPillClick,
		};
	},
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.ReviseToneQuizPillDemo {
  &--pills {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: @spacing-75;
    gap: @spacing-50;
  }
}
</style>
