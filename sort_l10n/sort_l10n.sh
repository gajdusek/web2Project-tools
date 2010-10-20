#!/bin/bash

if [ $# -eq 0 ]; then
cat <<EOF
Sort localization .inc files
Usage: $0 <file> <file> ...
EOF
fi

IFS=$'\n'
for file in $@; do
	backup_file=${file}~
	cp $file $backup_file
	sed -n '/^#/p' $backup_file >$file
	sed '/^#/d' $backup_file | LC_ALL=C sort -f >>$file
	fromdos $file
	rm -f $backup_file
done
