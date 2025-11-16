# Tests - Cyclops Project

## Quick Start

## Running tests from command line

```bash
# Run all tests manually
vendor/bin/phpunit --testdox

# Run specific test
vendor/bin/phpunit tests/Controller/HomeControllerTest.php

# Run with coverage
vendor/bin/phpunit --coverage-html var/coverage
```

## Test Structure

```
tests/
├── Command/          # CLI command tests
├── Controller/       # HTTP controller tests
│   ├── ApiDocsAuthControllerTest.php
│   ├── ApiKeyControllerTest.php
│   ├── CrisesControllerTest.php
│   ├── DashboardControllerTest.php
│   ├── EmailControllerTest.php
│   └── HomeControllerTest.php
├── Repository/       # Database repository tests
├── Security/         # Authentication & authorization tests
├── Service/          # Business logic tests
├── Traits/           # Reusable test traits
├── bootstrap.php     # Test bootstrap
└── prepare_env.php   # Environment setup
```

## Configuration

- **Database:** `mysql://user:password@127.0.0.1:3307/cyclops_test`
- **Environment:** Test (APP_ENV=test)
- **Config file:** `phpunit.xml.dist`

## Troubleshooting

### Tests fail with "Access denied" error

Grant permissions:
```bash
mysql -h 127.0.0.1 -P 3307 -u root -proot -e "GRANT ALL PRIVILEGES ON cyclops_test.* TO 'user'@'%'; FLUSH PRIVILEGES;"
```

### Tests fail with "Database not found"

Create database:
```bash
mysql -h 127.0.0.1 -P 3307 -u user -ppassword -e "CREATE DATABASE IF NOT EXISTS cyclops_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

## Test Coverage

Current test coverage:
- ✅ API Controllers
- ✅ Authentication & Security
- ✅ Command Line Tools
- ✅ Repositories
- ✅ Services

Total: **91 tests**

## Notes

- Tests use an isolated MySQL database (`cyclops_test`)
- Each test class resets the schema for consistency
- Mock services are used for external dependencies (AWS, Email)

