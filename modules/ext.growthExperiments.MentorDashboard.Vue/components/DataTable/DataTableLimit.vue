<template>
	<div class="data-table-limit">
		<cdx-select
			v-model:selected="selection"
			class="select"
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
		const optionLabelText = ( optionValue ) => {
			return {
				label: this.$i18n(
					'growthexperiments-mentor-dashboard-mentee-overview-show-entries',
					mw.language.convertNumber( optionValue )
				),
				value: optionValue
			};
		};
		const menuItems = this.limitOptions.map( optionLabelText );
		const selection = this.limit ? this.limit : menuItems[ 0 ].value;
		return {
			menuItems: menuItems,
			selection: selection
		};
	}
};
</script>

<style lang="less">
.data-table-limit {
	padding: 0 8px;

	.select {
		min-width: unset;
	}
}
</style>
