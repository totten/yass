#!/bin/bash

DRUSH=./drush.php

WORKDIR="$1"
shift

MASTER="$1"
shift

if [ -z "$WORKDIR" -o -z "$MASTER" -o -z "$1" ]; then
	echo "usage: $0 <workdir> <master-site> <replica-site-1> <replica-site-2> ..."
	exit
fi

[ -d "$WORKDIR" ] || mkdir -p "$WORKDIR"

echo "Sync all"
$DRUSH -l "$MASTER" yass-master-sync

for REPLICA in "$@" ; do
	echo "Read master as $REPLICA"
	$DRUSH -l "$MASTER" yass-export master "$REPLICA" > "${WORKDIR}/${REPLICA}-from-master.txt"
	cut -f2 -d/ "${WORKDIR}/${REPLICA}-from-master.txt" |sort -u> "${WORKDIR}/${REPLICA}-from-master.guid"
	
	echo "Read $REPLICA as master"
	$DRUSH -l "$MASTER" yass-export "$REPLICA" master > "${WORKDIR}/${REPLICA}-from-remote.txt"
	cut -f2 -d/ "${WORKDIR}/${REPLICA}-from-remote.txt" |sort -u> "${WORKDIR}/${REPLICA}-from-remote.guid"
	
	echo "Compare master and $REPLICA"
	sort < "${WORKDIR}/${REPLICA}-from-remote.txt" > "${WORKDIR}/${REPLICA}-from-remote.sort"
	sort < "${WORKDIR}/${REPLICA}-from-master.txt" > "${WORKDIR}/${REPLICA}-from-master.sort"
	diff -u "${WORKDIR}/${REPLICA}-from-remote.sort" "${WORKDIR}/${REPLICA}-from-master.sort" > "${WORKDIR}/${REPLICA}.diff"
	diff -u "${WORKDIR}/${REPLICA}-from-remote.guid" "${WORKDIR}/${REPLICA}-from-master.guid" > "${WORKDIR}/${REPLICA}.guid.diff"
done
