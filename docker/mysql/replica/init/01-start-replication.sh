#!/bin/bash
set -euo pipefail

echo "[replica] waiting for primary ${MYSQL_PRIMARY_HOST}..."

for i in $(seq 1 60); do
  if mysqladmin ping -h"${MYSQL_PRIMARY_HOST}" -u"${MYSQL_REPLICATION_USER}" -p"${MYSQL_REPLICATION_PASSWORD}" --silent --connect-timeout=2; then
    break
  fi

  if [ "$i" -eq 60 ]; then
    echo "[replica] timed out waiting for the primary"
    exit 1
  fi

  sleep 2
done

mysql --protocol=socket -uroot -p"${MYSQL_ROOT_PASSWORD}" <<-EOSQL
CHANGE REPLICATION SOURCE TO
  SOURCE_HOST='${MYSQL_PRIMARY_HOST}',
  SOURCE_PORT=3306,
  SOURCE_USER='${MYSQL_REPLICATION_USER}',
  SOURCE_PASSWORD='${MYSQL_REPLICATION_PASSWORD}',
  SOURCE_AUTO_POSITION=1,
  GET_SOURCE_PUBLIC_KEY=1;
START REPLICA;
GRANT REPLICATION CLIENT ON *.* TO '${MYSQL_USER}'@'%';
FLUSH PRIVILEGES;
EOSQL

echo "[replica] replication started"
