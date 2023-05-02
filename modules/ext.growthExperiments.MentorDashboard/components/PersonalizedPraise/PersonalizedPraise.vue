<template>
	<section class="ext-growthExperiments-PersonalizedPraise">
		<p>
			{{ $i18n(
				'growthexperiments-mentor-dashboard-personalized-praise-metrics',
				$filters.convertNumber( settings.minEdits ),
				$filters.convertNumber( settings.days )
			) }}
		</p>
		<personalized-praise-settings
			:settings-data="settings"
			@update:settings="onSettingsUpdate"
		></personalized-praise-settings>
		<no-results v-if="!hasData"></no-results>
		<user-card
			v-if="hasData"
			:mentee="mentee"
			@skip="onMenteeSkipped"
		></user-card>
		<personalized-praise-pagination
			v-if="hasData"
			:current-page="currentPage"
			:total-pages="totalPages"
			@previous="previousMentee"
			@next="nextMentee"
		></personalized-praise-pagination>
	</section>
</template>

<script>
const PersonalizedPraiseSettings = require( './PersonalizedPraiseSettings.vue' );
const PersonalizedPraisePagination = require( './PersonalizedPraisePagination.vue' );
const UserCard = require( './UserCard.vue' );
const NoResults = require( './NoResults.vue' );

// @vue/component
module.exports = exports = {
	compatConfig: { MODE: 3 },
	components: {
		PersonalizedPraiseSettings,
		PersonalizedPraisePagination,
		UserCard,
		NoResults
	},
	computed: {
		currentPage() {
			return this.$store.getters[ 'praiseworthyMentees/currentPage' ];
		},
		totalPages() {
			return this.$store.getters[ 'praiseworthyMentees/totalPages' ];
		},
		hasData() {
			return this.mentee !== undefined;
		},
		mentee() {
			return this.$store.getters[ 'praiseworthyMentees/mentee' ];
		},
		settings() {
			return this.$store.getters[ 'praiseworthyMentees/settings' ];
		}
	},
	methods: {
		previousMentee() {
			this.$store.dispatch( 'praiseworthyMentees/previousPage' );
		},
		nextMentee() {
			this.$store.dispatch( 'praiseworthyMentees/nextPage' );
		},
		onSettingsUpdate( settings ) {
			this.$store.dispatch( 'praiseworthyMentees/saveSettings', settings );
		},
		onMenteeSkipped( mentee ) {
			this.$store.dispatch( 'praiseworthyMentees/removeMentee', mentee );
		}
	},
	created() {
		this.$store.dispatch( 'praiseworthyMentees/fetchMentees' );
	}
};
</script>

<style lang="less">
@import ( reference ) '../../../../../../resources/lib/codex-design-tokens/theme-wikimedia-ui.less';

/* stylelint-disable-next-line selector-class-pattern */
.growthexperiments-mentor-dashboard-module-personalized-praise {
	position: relative;
}
</style>
