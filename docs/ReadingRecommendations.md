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
`mw.config.get( 'homepagemodules' )[ 'reading-recommendations' ].recommendations`,
next to `hasInterests`, which says whether the user has picked interest
articles during account setup.
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

### Interest-based recommendations

Up to four of the user's interest articles, read from the preference written
during account setup, each contribute one related article:

1. With more than four interests, the service takes four consecutive ones
   starting at the day number modulo the list length, so the interests shown
   rotate on consecutive days.
2. For each interest it runs one CirrusSearch `morelike:` query and keeps up
   to 50 candidates, most relevant first. The candidates are cached per
   interest for a week and shared by every user with that interest, so each
   interest is searched about once a week per wiki. The day is not part of
   that key: it picks from the candidate list rather than defining it, and
   the list only changes when the search index does. Keying it by day would
   expire every interest on the wiki at local midnight at once and would stop
   WANObjectCache from refreshing a busy entry before it expires, since each
   day would start from a key with no predecessor to refresh. In exchange, a
   newly written article takes up to a week to become a candidate.
3. The pick is the candidate at the day number modulo the candidate count,
   walking forward past any article that is one of the user's own interests
   or was already picked for another slot. Two users who share an interest
   pick from the identical candidate list, so they normally see the same
   article for it on the same day. The picks differ when the walk has to skip
   an article for one of them, because it is one of their other interests or
   already filled one of their earlier slots.

The list is a pure function of the interest list, the day, the candidate lists
and the pool, so a cache miss regenerates the same list as long as those are
unchanged.

Lists for users with interests are cached by date and a hash of the interest
list in its stored order, so changing interests refreshes the list at once.
Two users share an entry only when they picked the same interests in the same
order, which is rare, so in practice expect about one entry per user per day.
The entry saves the per-interest and pool cache reads on a hit, not the
searches, which are already shared. An interest whose search returns nothing
leaves its slot to the general recommendations.

### General recommendations

The remaining slots, and all four slots for a user with no interests, come from
the day's Featured pool, built by `FeaturedArticlePool`:

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

The service takes the pool in order from the front, skipping the user's own
interest articles and anything already picked. The pool itself is the same for
everyone on the wiki on a given day and changes every day, but which of its
articles fill a user's remaining slots depends on their interests: an article
that a user picked as an interest is passed over for that user only, so their
general recommendations are shifted against everyone else's. Because each day
is a fresh shuffle, an article can occasionally reappear on consecutive days;
the chance that a day's four overlap with the previous day's is about sixteen
divided by the number of articles in the category.

### Caching summary

| Cache entry | Key | Shared by | Lifetime |
| --- | --- | --- | --- |
| Featured pool | wiki, date, category, pool size | everyone on the wiki | one day, five minutes after a failed search |
| Related candidates | wiki, interest | everyone with that interest | one week, not cached after a failed search |
| Recommendation list | wiki, date, hash of the interest list | users whose interest lists are identical and in the same order, so rarely more than one user | one day |
