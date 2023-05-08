<template>
	<div>
		<span>Reading direction: </span><cdx-radio
			v-for="radio in radios"
			:key="'radio-' + radio.value"
			v-model="dialogDirection"
			name="inline-radios"
			:input-value="radio.value"
			:inline="true"
			@update:model-value="( newVal ) => $emit( 'update:readingDirection', newVal )"
		>
			{{ radio.label }}
		</cdx-radio>
	</div>
</template>

<script>
import { ref } from 'vue';
import { CdxRadio } from '@wikimedia/codex';

export default {
	name: 'DirectionRadioSelector',

	components: {
		CdxRadio
	},
	emits: [ 'update:readingDirection' ],
	setup() {
		const radios = [
			{
				label: 'LTR',
				value: 'ltr'
			},
			{
				label: 'RTL',
				value: 'rtl'
			}
		];

		const dialogDirection = ref( 'ltr' );

		return {
			dialogDirection,
			radios
		};
	}
};
</script>
