<template>
	<div class="ReviseToneQuizPillDemo">
		<h1>Quiz pill demo</h1>
		<cdx-button
			:disabled="!selectedValue"
			@click="resetDemo">
			Reset demo
		</cdx-button>
		<div class="ReviseToneQuizPillDemo--game demo-container">
			<quiz-game
				v-model:result="selectedValue"
				:data="quizData"
				@update:result="( newVal ) => selectedValue = newVal"
			>
			</quiz-game>
		</div>
	</div>
</template>

<script>
const { defineComponent, ref } = require( 'vue' );
const { CdxButton } = require( '@wikimedia/codex' );
const QuizGame = require( '../../revisetone/QuizGame.vue' );
const DATA = require( '../../revisetone/quizData.json' );

// @vue/component
module.exports = defineComponent( {
	name: 'ReviseToneQuizPillDemo',
	components: {
		CdxButton,
		QuizGame,
	},
	setup() {
		const selectedValue = ref( null );
		const resetDemo = () => {
			selectedValue.value = null;
		};
		return {
			quizData: DATA[ 0 ],
			resetDemo,
			selectedValue,
		};
	},
} );
</script>

<style lang="less">
.ReviseToneQuizPillDemo--game {
  margin: 0 auto;
  width: 400px;
}
</style>
