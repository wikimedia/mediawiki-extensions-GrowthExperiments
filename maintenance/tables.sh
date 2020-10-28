#!/bin/bash
ENGINES='mysql sqlite postgres'
TABLES='growthexperiments_link_recommendations'

DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"
for engine in $ENGINES; do
	for table in $TABLES; do
		mwscript generateSchemaSql.php --json $DIR/$table.json --type $engine --sql $DIR/$engine/$table.sql
	done
done
