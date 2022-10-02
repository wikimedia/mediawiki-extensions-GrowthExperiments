-- This file is automatically generated using maintenance/generateSchemaSql.php.
-- Source: GrowthExperiments/sql/growthexperiments_user_impact.json
-- Do not modify this file directly.
-- See https://www.mediawiki.org/wiki/Manual:Schema_changes
CREATE TABLE /*_*/growthexperiments_user_impact (
  geui_user_id INT UNSIGNED NOT NULL,
  geui_timestamp BINARY(14) NOT NULL,
  geui_data MEDIUMBLOB NOT NULL,
  INDEX geui_timestamp_user (geui_timestamp),
  PRIMARY KEY(geui_user_id)
) /*$wgDBTableOptions*/;