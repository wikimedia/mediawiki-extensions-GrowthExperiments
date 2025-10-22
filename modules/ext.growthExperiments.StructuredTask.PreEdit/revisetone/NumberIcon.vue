<template>
	<div class="ext-growthExperiments-NumberIcon">
		{{ convertedNumber }}
	</div>
</template>

<script>
const { defineComponent, computed, inject } = require( 'vue' );

// @vue/component
module.exports = exports = defineComponent( {
	name: 'NumberIcon',
	props: {
		number: {
			type: Number,
			default: null,
		},
	},
	setup( props ) {
		const { convertNumber } = inject( 'mw.language' );
		const convertedNumber = computed( () => convertNumber( props.number ) );
		return {
			convertedNumber,
		};
	},
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.ext-growthExperiments-NumberIcon {
  display: flex;
  align-items: center;
  justify-content: space-around;
  width: 20px;
  height: 20px;
  // Prevent shrinks produced by flex shrinking and line clamp ellipsis addition
  min-width: 20px;
  min-height: 20px;
  border-radius: 50%;
  border: 1px solid @border-color-interactive;
  background-color: @background-color-transparent;
  font-weight: @font-weight-bold;
}
</style>
