# Reading recommendations module

The `reading-recommendations` homepage module ("Daily reads") shows newcomers a
short list of articles to read on Special:Homepage. It is part of the reading
recommendations experiment (T435396) and is off by default.

## Settings

| Setting | Default | Purpose |
| --- | --- | --- |
| `$wgGEHomepageReadingRecommendationsEnabled` | `false` | Shows the module on Special:Homepage. |
| `$wgGEReadingRecommendationsFixtureFile` | `null` | Path of a JSON file whose rows the module shows instead of computed recommendations. Honored only when `$wgGEDeveloperSetup` is also `true`. |

## Developing the UI without CirrusSearch

The module can serve a fixture file, so the interface can be built and checked
on a bare wiki with no search stack, no Wikibase and no PageImages. Add to
LocalSettings.php:

```php
$wgGEHomepageReadingRecommendationsEnabled = true;
$wgGEDeveloperSetup = true;
$wgGEReadingRecommendationsFixtureFile = "$IP/extensions/GrowthExperiments/modules/ext.growthExperiments.Homepage.ReadingRecommendations/fixtures/recommendations.json";
```

The fixture holds four rows that cover every card state: an
interest-based recommendation with all fields, one without a thumbnail, a
general recommendation without a description, and one with every optional
field null. Point the setting at your own JSON file to stage other states.

The bundled article URLs assume `$wgArticlePath = '/wiki/$1'`. Adjust the URLs
in your own fixture if your wiki uses a different article path. The sample
articles may also need to be created or imported before their links resolve.

Omitted `description`, `thumbnail`, and `relatedTo` fields default to null.

## Data the module exports

`ReadingRecommendations::getJsData()` exports the rows through
`mw.config.get( 'homepagemodules' )[ 'reading-recommendations' ].recommendations`.
The same rows are rendered as a plain list inside the Vue mount point, so they
show without JavaScript and before the app mounts. Each row has:

| Field | Type | Notes |
| --- | --- | --- |
| `title` | string | Article title as displayed. |
| `description` | string or null | Short description, when available. |
| `thumbnail` | object or null | `url`, `width`, `height`. |
| `relatedTo` | string or null | Title of the user's interest the article relates to; null for a general recommendation. |
| `url` | string | Link to the article. |
| `pageId` | integer | |

The service that computes real recommendations from the user's interests and
the wiki's Featured articles is tracked in T436682.
