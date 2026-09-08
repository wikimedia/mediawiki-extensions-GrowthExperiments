# Reading recommendations module

The `reading-recommendations` homepage module ("Daily reads") shows newcomers a
short list of articles to read on Special:Homepage. It is part of the reading
recommendations experiment (T435396) and is off by default.

## Settings

| Setting | Default | Purpose |
| --- | --- | --- |
| `$wgGEHomepageReadingRecommendationsEnabled` | `false` | Shows the module on Special:Homepage. |
| `$wgGEHomepageReadingRecommendationsFeaturedCategory` | `""` | Full title of the category the general recommendations draw from, for example `Category:Featured articles`. Empty means no general recommendations. |
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

`ReadingRecommendationsFormatter` builds these rows, and its integration test
asserts that they match the committed fixture, so the two cannot drift apart.

## How recommendations are chosen

`ReadingRecommendationsService` builds a list of up to four articles once per
wiki-local day. The day comes from `WikiDay`: the date in `$wgLocaltimezone`,
or UTC when none is set, so the whole list rolls over at local midnight.

### General recommendations

All four slots come from the day's Featured pool, built by `FeaturedArticlePool`:

1. The pool draws from the category named by
   `$wgGEHomepageReadingRecommendationsFeaturedCategory`. An empty or invalid
   setting means an empty pool and no search.
2. On the first request of the day it runs one `incategory:` search in the
   main namespace, sorted randomly and limited to 50 results. The random sort
   is seeded with a checksum of the date, so CirrusSearch returns the same
   shuffled order for everyone who computes the pool that day, and a new date
   gives a new shuffle.
3. The result is cached in WANObjectCache under a key of wiki, date, category
   and pool size, and shared by all users, so changing the configured category
   takes effect on the next request rather than at the next local midnight. The
   entry lives for one day; a failed search is cached for five minutes only, so
   it is retried soon.

The service takes the first four articles of the pool. So the general
recommendations are the same for everyone on the wiki on a given day, and
they change every day. Because each day is a fresh
shuffle, an article can occasionally reappear on consecutive days; the chance
that a day's four overlap with the previous day's is about sixteen divided by
the number of articles in the category.
