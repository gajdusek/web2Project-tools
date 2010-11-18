#!/bin/bash
set -e

# set EOL_CONVERTOR to binary that will be used to convert end of lines for consistency reason (i.e. fromdos or todos)
# if given binary could not be found no coversion will be done
EOL_CONVERTOR=fromdos

EOL_CONVERTOR=$(which $EOL_CONVERTOR) || true
if [ -z "$EOL_CONVERTOR" ]; then
	cat >&2<<EOF

WARNING: 

Binary $EOL_CONVERTOR could not be found in your path, end of lines
in processed files may be inconsistent.  Please install "tofrodos"
debian package or corresponding package in your distribution or set
EOL_CONVERTOR variable in front of this script to your choice.

EOF
fi

if [ $# -eq 0 ]; then
cat <<EOF
No files given!

Usage: 
	$0 <file> <file> ...
	This will sort localization .inc files given as parameters. Bash
	wildcards in filenames will be parsed.

	All ends of lines will be converted to consistent style (default
	to unix style). This can be changed by editing EOL_CONVERTOR
	variable in front of this script.
Example:
	find web2project/locales -type f -iname '*.inc' -print0 | xargs -0 ./sort_l10n.sh
EOF
fi

IFS=$'\n'
for file in $@; do
	if [ ! -e "$file" ]; then echo "File not found: \"$fifle\"." >&2; continue; fi
	backup_file="${file}~"
	cp "$file" "$backup_file"
	sed -n '/^#/p' "$backup_file" >"$file"
	sed '/^#/d' "$backup_file" | LC_ALL=C sort -f -k2,2 -t "'" | uniq >>"$file"
	if [ -n "$EOL_CONVERTOR" ]; then $EOL_CONVERTOR "$file"; fi
	rm -f "$backup_file"
done
