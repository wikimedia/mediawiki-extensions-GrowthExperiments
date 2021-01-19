#!/bin/bash
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"
ENGINES='mysql sqlite postgres'
TABLES='growthexperiments_link_recommendations growthexperiments_link_submissions'
SCHEMA_CHANGES=`ls "$DIR"/schemaChanges/*.json | xargs -n1 basename`

for engine in $ENGINES; do
	for table in $TABLES; do
		mwscript generateSchemaSql.php --json "$DIR/schemas/$table.json" --type $engine --sql "$DIR/schemas/$engine/$table.sql"
	done
	for change in $SCHEMA_CHANGES; do
		mwscript generateSchemaChangeSql.php --json "$DIR/schemaChanges/$change" --type $engine --sql "$DIR/schemaChanges/$engine/${change%.json}.sql"
	done
done
