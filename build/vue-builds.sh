#!/bin/bash

root=$(pwd)
entry=$1

if [ ! -f "$entry" ]
then
	# FOLDER CHECKS
	if [ ! -d "$entry" ]
	then
		echo "$entry does not exists or is not a file nor a folder"
		exit 2
	else
		path=$entry
		backup="$path.orig"

		set -e

		echo "Backing up $path to $backup"
		cp -R $path $backup

		# Make the app, assuming the makefile is a folder up
		echo "Making $path"
		cd $path
		cd ../
		npm --silent install
		npm run --silent build
		
		# Reset
		cd $root

		# Compare build files
		echo "Comparing $path to $backup"
		if ! diff -qr $path $backup &>/dev/null
		then
			echo "$path build is NOT up-to-date! Please send the proper production build within the pull request"
			rm -Rf $backup
			exit 2
		else
			rm -Rf $backup
			echo "$path build is up-to-date"
		fi
	fi

# FILE CHECKS
# Chunks only works with names like xxxx.$entryfile
# If your files do not have the same suffix,
# please use folder checks
else
	path=$(dirname "$entry")
	file=$(basename $entry)

	set -e
	cd $path
	echo "Entering $path"

	# support for multiple chunks
	for chunk in *$file; do

		# Backup original file
		backupFile="$chunk.orig"
		echo "Backing up $chunk to $backupFile"
		cp $chunk $backupFile

	done

	# Make the app
	echo "Making $file"
	cd ../
	npm --silent install
	npm run --silent build

	# Reset
	cd $root
	cd $path

	# support for multiple chunks
	for chunk in *$file; do

		# Compare build files
		echo "Comparing $chunk to the original"
		backupFile="$chunk.orig"
		if ! diff -q $chunk $backupFile &>/dev/null
		then
			echo "$chunk build is NOT up-to-date! Please send the proper production build within the pull request"
			exit 2
		else
			echo "$chunk build is up-to-date"
		fi

	done
fi
