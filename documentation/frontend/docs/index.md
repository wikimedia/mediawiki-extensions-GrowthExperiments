# GrowthExperiments Frontend Documentation

This site provides documentation to showcase the Vue components used in building
the frontend for GrowthExperiments features.

It can also serve for creating UI prototypes using [Vue 3](https://vuejs.org/)
and Wikimedia's [Codex](https://doc.wikimedia.org/codex) library.

To learn how to contribute to this documentation please see the repository's
[README.md](https://gerrit.wikimedia.org/r/plugins/gitiles/mediawiki/extensions/GrowthExperiments/+/refs/heads/master/documentation/frontend/README.md)
file.

## Demos

### Complex components

These are Vue and Codex demos of GrowthExperiments interfaces.

- [Add Link dialog](./demos/add-link-dialog)
- [Add Image dialog](./demos/add-image-dialog)

### Low-level components

These are demos for components used for building the _Complex components_ demos above. These
are candidates to be eventually upstreamed to Codex or re-used by other teams.

- [On-boarding dialog](./demos/onboarding-dialog) ([T332767](https://phabricator.wikimedia.org/T332767))
- [MultiPane component](./demos/multi-pane) ([T336750](https://phabricator.wikimedia.org/T336750))
- [Stepper component](./demos/onboarding-stepper) ([T333584](https://phabricator.wikimedia.org/T333584))
- [Filter dialog](./demos/filter-dialog) ([T335752](https://phabricator.wikimedia.org/T335752))

## Importing components into MediaWiki

Due to MediaWiki's ResourceLoader constraints on ES modules usage and the
limited support for Vue SFCs in MediaWiki (see [Use_Single-file_components](https://www.mediawiki.org/wiki/Vue.js#Use_Single-file_components))
it's not possible to document the existing GrowthExperiments components
with VitePress. Therefore the existing components under:

- `/modules/vue-components`
- `/modules/ext.growthExperiments.MentorDashboard`
- `/modules/ext.growthExperiments.Homepage.NewImpact`

can't be directly imported into the **docs** project. Alternatives to
solve this problem are being considered in [Phab T328125](https://phabricator.wikimedia.org/T328125).
