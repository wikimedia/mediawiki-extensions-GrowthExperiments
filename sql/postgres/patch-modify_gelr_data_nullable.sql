-- This file is automatically generated using maintenance/generateSchemaChangeSql.php.
-- Source: sql/abstractSchemaChanges/patch-modify_gelr_data_nullable.json
-- Do not modify this file directly.
-- See https://www.mediawiki.org/wiki/Manual:Schema_changes
ALTER TABLE growthexperiments_link_recommendations
  ALTER gelr_data
  DROP NOT NULL;
