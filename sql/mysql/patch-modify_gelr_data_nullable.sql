-- This file is automatically generated using maintenance/generateSchemaChangeSql.php.
-- Source: sql/abstractSchemaChanges/patch-modify_gelr_data_nullable.json
-- Do not modify this file directly.
-- See https://www.mediawiki.org/wiki/Manual:Schema_changes
ALTER TABLE /*_*/growthexperiments_link_recommendations
  CHANGE gelr_data gelr_data MEDIUMBLOB DEFAULT NULL;
