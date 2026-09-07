# Testing Guide for OrdinaTrack API

This document describes how to run the test suite for the OrdinaTrack API.

## Prerequisites

- PHP 8.0 or higher
- MySQL 5.7 or higher
- Composer

## Setup

### 1. Install Testing Dependencies

```bash
cd API
composer install
```

This will install PHPUnit and Mockery for testing.

### 2. Create Test Database

```bash
# Create the test database
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS ordinatrack_test;"
```

### 3. Create .env.testing File (Optional)

For custom test database credentials, create a `.env.testing` file:

```env
DB_HOST=localhost
DB_PORT=3306
DB_NAME=ordinatrack_test
DB_USER=root
DB_PASS=
JWT_SECRET=test-secret-key-for-jwt-tokens
```

## Running Tests

### Run All Tests

```bash
cd API
./vendor/bin/phpunit
```

### Run Specific Test Suite

```bash
# Run only unit tests
./vendor/bin/phpunit tests/Unit

# Run only integration tests
./vendor/bin/phpunit tests/Integration
```

### Run Specific Test Class

```bash
# Run only SchemaTest
./vendor/bin/phpunit tests/Unit/SchemaTest.php
```

### Run Specific Test Method

```bash
# Run only one test method
./vendor/bin/phpunit tests/Unit/SchemaTest.php --filter testSchemaInitializationCreatesAllTables
```

### Generate Code Coverage Report

```bash
# Generate HTML coverage report
./vendor/bin/phpunit --coverage-html coverage/

# Generate text coverage report
./vendor/bin/phpunit --coverage-text
```

## Test Structure

### Schema Tests (tests/Unit/SchemaTest.php)

Tests the database schema manager functionality:

- ✅ Schema initialization creates all tables
- ✅ Users table structure validation
- ✅ Organizations table structure validation
- ✅ Geographic hierarchy (Province → District → Branch)
- ✅ RBAC (Role-Based Access Control) structure
- ✅ Default roles insertion
- ✅ Default permissions insertion
- ✅ Organization members junction table
- ✅ Soft delete capability
- ✅ Timestamps on all tables
- ✅ Audit logs table structure
- ✅ Cascade delete relationships
- ✅ Member roles relationships
- ✅ Logistics table structure
- ✅ Requests workflow table
- ✅ Folders nested structure
- ✅ Schema reset functionality
- ✅ Re-initialization after reset
- ✅ Idempotent initialization

## Test Cases Coverage

### Table Structure Tests
- Verify all 16 database tables are created
- Validate column names and types
- Check for required fields
- Verify unique constraints
- Validate foreign key relationships

### Relationship Tests
- One-to-Many relationships (Organization → Districts)
- Many-to-Many relationships (Users ↔ Roles)
- Self-referential relationships (Folders → Folders)
- Cascade delete operations

### Data Integrity Tests
- Default roles are inserted (8 roles)
- Default permissions are inserted (21 permissions)
- Soft delete columns exist
- Timestamps are present

### Idempotency Tests
- Schema initialization is safe to call multiple times
- Tables aren't re-created if they already exist
- No errors on duplicate initialization

## CI/CD Integration

### GitHub Actions Example

Add this to `.github/workflows/tests.yml`:

```yaml
name: Run Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: ordinatrack_test
        options: >-
          --health-cmd="mysqladmin ping"
          --health-interval=10s
          --health-timeout=5s
          --health-retries=3
    
    steps:
      - uses: actions/checkout@v3
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: pdo, pdo_mysql, json
      
      - name: Install dependencies
        run: cd API && composer install
      
      - name: Run tests
        run: cd API && ./vendor/bin/phpunit
```

## Troubleshooting

### Database Connection Errors

If you get database connection errors:

1. Verify MySQL is running: `mysql -u root -p`
2. Check database exists: `mysql -u root -p -e "SHOW DATABASES;"`
3. Verify credentials in `.env.testing` or `phpunit.xml`

### Permission Denied Errors

If PHPUnit can't run:

```bash
chmod +x ./vendor/bin/phpunit
```

### Memory Errors

For large test suites, increase memory limit:

```bash
php -d memory_limit=-1 ./vendor/bin/phpunit
```

## Best Practices

1. **Test Isolation**: Each test resets the database before and after
2. **Descriptive Names**: Test method names clearly describe what they test
3. **Single Responsibility**: Each test verifies one specific behavior
4. **Clear Assertions**: Use descriptive assertion messages
5. **Cleanup**: Always clean up after tests

## Adding New Tests

When adding new tests:

1. Create test class in appropriate directory (Unit/Integration)
2. Extend `PHPUnit\Framework\TestCase`
3. Use `@test` annotation or `test` prefix for test methods
4. Setup/teardown database state in `setUp()` and `tearDown()`
5. Use descriptive test names
6. Document complex test logic with comments

Example:

```php
/**
 * Test example
 * 
 * @test
 */
public function testNewFeatureBehavior(): void
{
    // Arrange
    Schema::initialize();
    
    // Act
    $result = $this->performAction();
    
    // Assert
    $this->assertTrue($result);
}
```

## Resources

- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [PHPUnit Best Practices](https://phpunit.de/best-practices.html)
- [MySQL Information Schema](https://dev.mysql.com/doc/refman/8.0/en/information-schema.html)
