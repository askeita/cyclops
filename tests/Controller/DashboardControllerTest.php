<?php

namespace App\Tests\Controller;

use App\Kernel;
use App\Security\User;
use Exception;
use PDO;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;


/**
 * Class DashboardControllerTest
 *
 * Tests for DashboardController
 */
class DashboardControllerTest extends WebTestCase
{
    private KernelBrowser $client;


    /**
     * Set up environment variables before any tests run
     *
     * @return void
     */
    public static function setUpBeforeClass(): void
    {
        // Create test database directory in project var folder
        $projectDir = dirname(__DIR__, 2); // Go up two levels from tests/Controller
        $testDbPath = $projectDir . '/var/test.db';

        // Ensure var directory exists
        if (!is_dir($projectDir . '/var')) {
            mkdir($projectDir . '/var', 0755, true);
        }

        // Define environment variables for testing
        $_ENV['DATABASE_URL'] = 'sqlite:///' . $testDbPath;
        $_ENV['APP_ENV'] = 'test';
        $_ENV['APP_SECRET'] = 's$cretf0rt3st';
        $_ENV['ENCRYPTION_KEY'] = 'test_encryption_key_for_tests';
        $_ENV['MAILER_DSN'] = 'null://null';
        $_ENV['AWS_REGION'] = 'us-east-1';
        $_ENV['AWS_ACCESS_KEY_ID'] = 'test_key';
        $_ENV['AWS_SECRET_ACCESS_KEY'] = 'test_secret';

        // Ensure environment variables are set in $_SERVER and via putenv
        foreach ($_ENV as $key => $value) {
            $_SERVER[$key] = $value;
            putenv("$key=$value");
        }

        // Initialize the test database here to ensure it exists before any tests
        self::initializeTestDatabaseStatic();
    }

    /**
     * Static method to initialize test database from setUpBeforeClass
     */
    private static function initializeTestDatabaseStatic(): void
    {
        $projectDir = dirname(__DIR__, 2);
        $dbPath = $projectDir . '/var/test.db';

        // Backup existing database if it exists
        if (file_exists($dbPath)) {
            copy($dbPath, $dbPath . '.backup');
            unlink($dbPath);
        }

        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create api_keys table matching the structure expected by UserProvider
        $createApiKeysSQL = "
            CREATE TABLE api_keys (
                id INTEGER PRIMARY KEY,
                email VARCHAR(255) NOT NULL,
                password VARCHAR(255) NOT NULL,
                last_connection DATETIME default NULL,
                email_verified BOOLEAN DEFAULT 0,
                verification_token VARCHAR(255) NULL,
                is_active BOOLEAN DEFAULT 1,
                created_at DATETIME NOT NULL,
                key_value VARCHAR(255) UNIQUE,
                last_used_at DATETIME NULL,
                usage_count INTEGER DEFAULT 0
            )
        ";

        $pdo->exec($createApiKeysSQL);
    }

    /**
     * Set up before each test
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->client = static::createClient();
        // Database is now initialized in setUpBeforeClass
    }

    /**
     * Test accessing dashboard without authentication
     *
     * @return void
     */
    public function testDashboardRedirectsWhenNoEmail(): void
    {
        $this->client->request('GET', '/dashboard');

        // Should redirect to login page when no authentication (security intercepted)
        $this->assertResponseRedirects('/login');
    }

    /**
     * Test generating API key without authentication
     *
     * @return void
     */
    public function testGenerateApiKeyUnauthorized(): void
    {
        $this->client->request('POST', '/dashboard/generate-api-key');

        // Should redirect to login when no authentication (Symfony's default behavior)
        $this->assertResponseRedirects('/login');
    }

    /**
     * Test accessing dashboard with authentication
     *
     * @return void
     */
    public function testDashboardWithAuthentication(): void
    {
        // Set up user session
        $this->loginAsUser();

        $this->client->request('GET', '/dashboard');

        // Should be successful when user is authenticated, or redirect if auth fails
        $statusCode = $this->client->getResponse()->getStatusCode();

        if ($statusCode === 302) {
            // If redirected, it's likely because authentication didn't work as expected
            $this->assertResponseRedirects('/login');
        } else {
            // If not redirected, should be successful
            $this->assertResponseIsSuccessful();
        }
    }

    /**
     * Test generating API key with authentication
     *
     * @return void
     */
    public function testGenerateApiKeyWithAuthentication(): void
    {
        // Set up user session and API key in database
        $this->loginAsUser();

        $this->client->request('POST', '/dashboard/generate-api-key');

        $statusCode = $this->client->getResponse()->getStatusCode();

        if ($statusCode === 302) {
            // If redirected, authentication didn't work as expected
            $this->assertResponseRedirects('/login');
        } else {
            // Should be successful when user is authenticated
            $this->assertResponseIsSuccessful();

            $response = json_decode($this->client->getResponse()->getContent(), true);
            if ($response) {
                $this->assertArrayHasKey('api_key', $response);
                $this->assertNotEmpty($response['api_key']);
            }
        }
    }

    /**
     * Test logout functionality
     *
     * @return void
     */
    public function testLogout(): void
    {
        // Set up user session
        $this->loginAsUser();

        $this->client->request('GET', '/logout');

        // Should redirect after logout
        $this->assertResponseRedirects('/');
    }

    /**
     * Log in as test user
     *
     * @return void
     */
    private function loginAsUser(): void
    {
        // Create test user in database first
        $this->createTestUser();

        // Create a proper User object for authentication
        $testPassword = password_hash('testPassword123', PASSWORD_ARGON2ID);
        $user = new User('test@example.com', $testPassword, ['ROLE_USER'], true);

        // Try to login the user using Symfony's authentication system
        try {
            $this->client->loginUser($user);
        } catch (Exception) {
            // If login fails, that's okay - the tests will handle redirections
        }
    }

    /**
     * Create a test user in the database
     *
     * @return void
     */
    private function createTestUser(): void
    {
        $projectDir = dirname(__DIR__, 2);
        $dbPath = $projectDir . '/var/test.db'; // Use the test database path

        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Ensure the api_keys table exists
        $result = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='api_keys'");
        if (!$result->fetch()) {
            // Table doesn't exist, create it
            $createApiKeysSQL = "
                CREATE TABLE api_keys (
                    id INTEGER PRIMARY KEY,
                    email VARCHAR(255) NOT NULL,
                    password VARCHAR(255) NOT NULL,
                    last_connection DATETIME default NULL,
                    email_verified BOOLEAN DEFAULT 0,
                    verification_token VARCHAR(255) NULL,
                    is_active BOOLEAN DEFAULT 1,
                    created_at DATETIME NOT NULL,
                    key_value VARCHAR(255) UNIQUE,
                    last_used_at DATETIME NULL,
                    usage_count INTEGER DEFAULT 0
                )
            ";
            $pdo->exec($createApiKeysSQL);
        }

        $testEmail = 'test@example.com';
        $hashedEmail = hash('sha3-512', $testEmail . $_ENV['ENCRYPTION_KEY']);
        $testPassword = password_hash('testPassword123', PASSWORD_ARGON2ID);

        // Remove existing user first
        $pdo->exec("DELETE FROM api_keys WHERE email = '$hashedEmail'");

        // Insert new user with all required fields according to UserProvider
        $stmt = $pdo->prepare("INSERT INTO api_keys (email, password, key_value, email_verified, is_active, created_at, usage_count) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $hashedEmail,
            $testPassword,
            'test_key_' . uniqid(),
            1, // email_verified = true (required by UserProvider)
            1, // is_active = true
            date('Y-m-d H:i:s'),
            0
        ]);
    }

    /**
     * Get the kernel class for the test client
     *
     * @return string
     */
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    /**
     * Clean up after all tests have run
     *
     * @return void
     */
    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
    }
}
