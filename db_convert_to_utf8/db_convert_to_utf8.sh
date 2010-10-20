#!/bin/bash

usage()
{
cat << EOF
usage: $0 options

This script analyze web2Project database and output SQL script to convert string to utf8.

OPTIONS:
	Same as mysqladmin options, 
	i.e: $0 -h <host> -u <user> -p[<password>] <database>
EOF
}

if [ $# -eq 0 ]; then
     usage
     exit 1
fi

mysql="mysql $*"
oldcharset=$($mysql variables | grep '^[[:blank:]]*default-character-set' | awk '{print $2}')
database=$(echo 'SELECT DATABASE()' | $mysql | sed '1d');
echo '# Current database connection charset is '$oldcharset;
if [ $oldcharset == "utf8" ]; then
	echo "# Current database connection charset is already utf8. No database conversion needed. Please set \$dbcharset config file value to 'utf8' and you are done.";
	exit
fi
echo "# SQL statements for database conversion to utf8 will be printed. Run these SQL statements (i.e. within phpmyadmin or other mysql client) and";
echo "# then set \$w2Pconfig['dbcharset'] config file value to 'utf8'.";
echo "# WARNING: Script will convert all tables in the database. Tables' prefix is not supported (because it is not fully supported by current web2Project version)."

echo "# IMPORTANT WARNING: Make sure tables content will not be updated until you finish database conversion and set new \$w2Pconfig['dbcharset'] value!";
echo

echo "USE \`$database\`;"
echo

excluded_tables=(
	config_list
	event_queue
	gacl_aco
	gacl_aco_map
	gacl_aco_sections
	gacl_axo_map
	gacl_phpgacl
	sessions
	user_access_log
	user_feeds
	w2pversion
)

altered_tables=$(tempfile)

for table in $(echo 'show tables;'|$mysql|sed '1d'); do
	exclude=0
	for i in ${excluded_tables[@]}; do if [ $i == ${table} ] ; then exclude=1; fi; done
	if [ $exclude -eq 1 ] ; then continue; fi
	echo "describe $table;"|$mysql|sed '1d' | gawk '{print "'$table'\t"$1"\t"$2}' | egrep -i "(char|text)"
done | 
while read table col a_type; do
	type=$(echo "$a_type" |sed 's/(.*//');
	type_size=$(echo "$a_type" |sed 's/.*(\(.*\)).*/\1/')
	#echo "$table $col $a_type $type $type_size"		
	case $type in
		'char') new_type='binary' ;;
		'text') new_type='blob' ;;
		'tinytext') new_type='tinyblob' ;;
		'mediumtext') new_type='mediumblob' ;;
		'longtext') new_type='longblob' ;;
		'varchar') new_type='varbinary('$type_size')'; type=$a_type ;;
		*) exit 1 ;;
	esac
	echo 'ALTER TABLE `'$table'` CHANGE `'$col'` `'$col'` '$type' CHARACTER SET `'$oldcharset'`;'
	echo 'ALTER TABLE `'$table'` CHANGE `'$col'` `'$col'` '$new_type';'
	echo 'ALTER TABLE `'$table'` CHANGE `'$col'` `'$col'` '$type' CHARACTER SET `utf8`;'
	echo
	echo $table >>$altered_tables
done

echo
cat $altered_tables|uniq|while read table; do
	echo 'REPAIR TABLE `'$table'` QUICK;'	
done
rm $altered_tables
