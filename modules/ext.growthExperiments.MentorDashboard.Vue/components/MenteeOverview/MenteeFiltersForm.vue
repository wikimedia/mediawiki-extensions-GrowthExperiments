<template>
	<form
		id="mentee-filters-form"
		ref="formRef"
		class="ext-growthExperiments-MenteeFiltersForm"
		tabindex="0"
		@keyup.esc="$emit( 'close' )"
		@submit="onFiltersUpdate"
	>
		<h3 class="no-gutter">
			{{ $i18n( 'growthexperiments-mentor-dashboard-mentee-overview-add-filter-total-edits-headline' ) }}
		</h3>
		<section class="ext-growthExperiments-MenteeFiltersForm__form-group">
			<label>
				{{ $i18n( 'growthexperiments-mentor-dashboard-mentee-overview-add-filter-total-edits-from' ) }}
			</label>
			<c-number-input
				v-model="formData.editCountMin"
				min="0"
				step="1"
			></c-number-input>
		</section>
		<section class="ext-growthExperiments-MenteeFiltersForm__form-group">
			<label>
				{{ $i18n( 'growthexperiments-mentor-dashboard-mentee-overview-add-filter-total-edits-to' ) }}
			</label>
			<c-number-input
				v-model="formData.editCountMax"
				min="0"
				step="1"
			></c-number-input>
		</section>
		<horizontal-divider class="ext-growthExperiments-MenteeFiltersForm-section-divider"></horizontal-divider>
		<h3 class="no-gutter">
			{{ $i18n( 'growthexperiments-mentor-dashboard-mentee-overview-add-filter-active-days-ago-headline' ) }}
		</h3>
		<section>
			<div class="ext-growthExperiments-MenteeFiltersForm__form-group--inline">
				<label>
					{{ $i18n( 'growthexperiments-mentor-dashboard-mentee-overview-add-filter-active-days-ago-days' ) }}
				</label>
				<div class="ext-growthExperiments-MenteeFiltersForm__button-group">
					<cdx-button
						v-for="option in dayLabels"
						:key="option.value"
						:type="option.selected ? 'primary' : null"
						:action="option.selected ? 'progressive' : null"
						:class="{ 'button-group__button--selected': option.selected }"
						:alt="option.altText"
						@click.prevent="onDaysAgoUpdate( option.value )"
					>
						{{ option.displayText }}
					</cdx-button>
				</div>
			</div>
			<div class="ext-growthExperiments-MenteeFiltersForm__form-group--inline">
				<label>
					{{ $i18n( 'growthexperiments-mentor-dashboard-mentee-overview-add-filter-active-days-ago-months' ) }}
				</label>
				<div class="ext-growthExperiments-MenteeFiltersForm__button-group">
					<cdx-button
						v-for="option in monthLabels"
						:key="option.value"
						:type="option.selected ? 'primary' : null"
						:action="option.selected ? 'progressive' : null"
						:alt="option.altText"
						@click.prevent="onDaysAgoUpdate( option.value )"
					>
						{{ option.displayText }}
					</cdx-button>
				</div>
			</div>
		</section>
		<horizontal-divider class="ext-growthExperiments-MenteeFiltersForm-section-divider"></horizontal-divider>
		<section class="ext-growthExperiments-MenteeFiltersForm__form-group">
			<cdx-checkbox v-model="formData.onlyStarred" @update:model-value="onOnlyStarredUpdate">
				{{ $i18n( 'growthexperiments-mentor-dashboard-mentee-overview-add-filter-starred-only-starred' ) }}
			</cdx-checkbox>
		</section>
		<section class="ext-growthExperiments-MenteeFiltersForm__form-group ext-growthExperiments-MenteeFiltersForm__form-actions">
			<cdx-button class="ext-growthExperiments-utils__pull-right" form="mentee-filters-form" type="submit">
				{{
					$i18n( 'growthexperiments-mentor-dashboard-mentee-overview-add-filter-submit' )
				}}
			</cdx-button>
		</section>
	</form>
</template>

<script>
const { CdxButton, CdxCheckbox } = require( '@wikimedia/codex' );
const HorizontalDivider = require( '../HorizontalDivider/HorizontalDivider.vue' );
const CNumberInput = require( '../CNumberInput/CNumberInput.vue' );

const TIME_AGO_LABELS = {
	DAYS: {
		label: 'growthexperiments-mentor-dashboard-mentee-overview-add-filter-active-days-ago-days-title',
		values: [ 1, 3, 7 ]
	},
	MONTHS: {
		label: 'growthexperiments-mentor-dashboard-mentee-overview-add-filter-active-months-ago-days-title',
		values: [ 30, 60, 180 ]
	}
};

// @vue/component
module.exports = exports = {
	compatConfig: { MODE: 3 },
	components: {
		CNumberInput,
		CdxButton,
		CdxCheckbox,
		HorizontalDivider
	},
	props: {
		activeDaysAgo: { type: Number, default: undefined },
		editCountMin: { type: [ Number, String ], default: undefined },
		editCountMax: { type: [ Number, String ], default: undefined },
		onlyStarred: { type: Boolean, default: false }
	},
	emits: [ 'close', 'update:filters' ],
	data() {
		return {
			formData: {
				activeDaysAgo: this.activeDaysAgo,
				editCountMin: this.editCountMin,
				editCountMax: this.editCountMax,
				onlyStarred: this.onlyStarred
			}
		};
	},
	computed: {
		dayLabels() {
			return TIME_AGO_LABELS.DAYS.values.map( ( val ) => {
				return {
					selected: this.formData.activeDaysAgo === val,
					altText: this.$i18n(
						TIME_AGO_LABELS.DAYS.label,
						val,
						mw.language.convertNumber( val )
					),
					displayText: val,
					value: val
				};
			} );
		},
		monthLabels() {
			return TIME_AGO_LABELS.MONTHS.values.map( ( val ) => {
				return {
					selected: this.formData.activeDaysAgo === val,
					altText: this.$i18n(
						TIME_AGO_LABELS.MONTHS.label,
						val,
						mw.language.convertNumber( val )
					),
					displayText: val / 30,
					value: val
				};
			} );
		}
	},
	methods: {
		onFiltersUpdate() {
			const filters = {
				onlyStarred: this.formData.onlyStarred,
				// Falsy values in activeDaysAgo (null), editCountMin (''), editCountMin ('')
				// are used to remove the filter
				activeDaysAgo: this.formData.activeDaysAgo && Number( this.formData.activeDaysAgo ),
				editCountMin: this.formData.editCountMin && Number( this.formData.editCountMin ),
				editCountMax: this.formData.editCountMax && Number( this.formData.editCountMax )
			};
			this.$emit( 'update:filters', filters );
		},
		onDaysAgoUpdate( daysAgo ) {
			if ( this.formData.activeDaysAgo === daysAgo ) {
				this.formData.activeDaysAgo = NaN;
			} else {
				this.formData.activeDaysAgo = daysAgo;
			}
		},
		onOnlyStarredUpdate( value ) {
			this.formData.onlyStarred = value;
		}
	},
	mounted() {
		this.$refs.formRef.focus();
	}
};
</script>

<style lang="less">
@import '../variables.less';

.ext-growthExperiments-MenteeFiltersForm {
	padding: @padding-horizontal-base;

	> h3 {
		.no-gutter();
	}

	&-section-divider {
		margin: 16px 0;
	}

	&__form-group {
		margin-top: 12px;

		&--inline {
			display: inline-block;
			margin-right: 16px;
		}
	}

	&__form-actions {
		padding: 8px 0;
	}

	&__button-group {
		padding: 8px 0;
		display: flex;

		> *:first-of-type:not( :last-of-type ) {
			border-start-end-radius: 0;
			border-end-end-radius: 0;
		}

		> *:not( :first-of-type ):not( :last-of-type ) {
			border-radius: 0;
		}

		> *:not( :first-of-type ):last-of-type {
			border-start-start-radius: 0;
			border-end-start-radius: 0;
		}
	}
}
</style>
