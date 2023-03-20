<template>
	<div class="ext-growthExperiments-DataTableLimit">
		<cdx-select
			v-model:selected="selection"
			class="ext-growthExperiments-DataTableLimit__select"
			:menu-items="menuItems"
			@update:selected="$emit( 'update', $event )"
		></cdx-select>
	</div>
</template>

<script>
const { CdxSelect } = require( '@wikimedia/codex' );
// @vue/component
module.exports = exports = {
	compatConfig: { MODE: 3 },
	components: {
		CdxSelect
	},
	props: {
		limitOptions: { type: Array, required: true },
		limit: { type: Number, required: true }
	},
	emits: [ 'update' ],
	data() {
		const selection = this.limit ? this.limit : this.limitOptions[ 0 ].value;
		return {
			selection: selection
		};
	},
	computed: {
		menuItems() {
			return this.limitOptions.map( ( optionValue ) => {
				return {
					label: this.$i18n(
						'growthexperiments-mentor-dashboard-mentee-overview-show-entries',
						this.$filters.convertNumber( optionValue )
					).text(),
					value: optionValue
				};
			} );
		}
	}
};
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.ext-growthExperiments-DataTableLimit {
	padding: @spacing-25 @spacing-75;

	&__select {
		min-width: unset;
	}
}
</style>
