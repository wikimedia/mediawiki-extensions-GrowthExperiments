-- This file is automatically generated using maintenance/generateSchemaSql.php.
-- Source: GrowthExperiments/sql/growthexperiments_user_impact.json
-- Do not modify this file directly.
-- See https://www.mediawiki.org/wiki/Manual:Schema_changes
CREATE TABLE /*_*/growthexperiments_user_impact (
  geui_user_id INTEGER UNSIGNED NOT NULL,
  geui_timestamp BLOB NOT NULL,
  geui_data BLOB NOT NULL,
  PRIMARY KEY(geui_user_id)
);

CREATE INDEX geui_timestamp_user ON /*_*/growthexperiments_user_impact (geui_timestamp);