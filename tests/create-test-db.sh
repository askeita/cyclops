#!/bin/bash
# MySQL initialization script for test database

set -e

echo "Creating test database and granting permissions..."

mysql -u root -p"$MYSQL_ROOT_PASSWORD" <<-EOSQL
    CREATE DATABASE IF NOT EXISTS cyclops_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    GRANT ALL PRIVILEGES ON cyclops_test.* TO '$MYSQL_USER'@'%';
    FLUSH PRIVILEGES;
    SELECT 'Test database created and permissions granted' AS Status;
EOSQL

echo "Test database setup completed."

