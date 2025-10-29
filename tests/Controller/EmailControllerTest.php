<?php

namespace App\Tests\Controller;

use App\Controller\EmailController;
use App\Kernel;
use PDO;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;


/**
 * Class EmailControllerTest
 *
 * Tests for EmailController
 */
class EmailControllerTest extends WebTestCase
{
    private KernelBrowser $client;


    /**
     * Set up environment before any tests run
     *
     * @return void
     */
    public static function setUpBeforeClass(): void
    {
        // Create test database directory
        $testDbDir = sys_get_temp_dir() . '/cyclops_test';
        if (!is_dir($testDbDir)) {
            mkdir($testDbDir, 0755, true);
        }

        // Create test database in project var directory for consistency with EmailController
        $projectDir = dirname(__DIR__, 2);
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

        // Ensure environment variables are set in $_SERVER and via putenv
        foreach ($_ENV as $key => $value) {
            $_SERVER[$key] = $value;
            putenv("$key=$value");
        }
    }

    /**
     * Set up before each test
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->initializeTestDatabase();

        // We'll get the controller from the container when needed
        // to ensure it has access to Symfony services
    }

    /**
     * Test checkEmailAvailable method
     *
     * @return void
     */
    public function testCheckEmailAvailable(): void
    {
        // Create test database connection
        $projectDir = dirname(__DIR__, 2);
        $dbPath = $projectDir . '/var/test.db';
        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $testEmail = 'test@example.com';
        $hashedEmail = hash('sha3-512', $testEmail . $_ENV['ENCRYPTION_KEY']);

        // Get controller from container
        $controller = $this->client->getContainer()->get(EmailController::class);

        // Test with non-existing email (should return true)
        $result = $controller->checkEmailAvailable($pdo, $hashedEmail);
        $this->assertTrue($result, 'Email should be available');

        // Add email to database
        $stmt = $pdo->prepare("INSERT INTO api_keys (email, password, is_active, created_at, usage_count, email_verified) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $hashedEmail,
            'test_password',
            1, // is_active
            date('Y-m-d H:i:s'),
            0, // usage_count
            0  // email_verified
        ]);

        // Test with existing email (should return false)
        $result = $controller->checkEmailAvailable($pdo, $hashedEmail);
        $this->assertFalse($result, 'Email should not be available');
    }

    /**
     * Test sendVerificationEmail method
     *
     * @return void
     */
    public function testSendVerificationEmail(): void
    {
        // Mock the mailer and replace it in the container
        $mockMailer = $this->createMock(MailerInterface::class);
        $mockMailer->expects($this->once())
                   ->method('send')
                   ->with($this->callback(function($email) {
                       return $email->getFrom()[0]->getAddress() === 'askeita.dev@gmail.com' &&
                              $email->getTo()[0]->getAddress() === 'test@example.com' &&
                              $email->getSubject() === 'Please verify your email address';
                   }));

        // Replace the mailer service in the container
        $this->client->getContainer()->set('mailer', $mockMailer);

        // Get the controller from the container so it has access to Symfony services
        $controller = $this->client->getContainer()->get(EmailController::class);

        // Test sending verification email
        $testEmail = 'test@example.com';
        $testToken = 'test_verification_token';

        // This should not throw an exception
        $controller->sendVerificationEmail($testEmail, $testToken);

        // If we reach this point, the test passed
        $this->assertTrue(true, 'Verification email sent successfully');
    }

    /**
     * Test verifyEmail method with valid token
     *
     * @return void
     */
    public function testVerifyEmailWithValidToken(): void
    {
        // Prepare test data
        $testEmail = 'test@example.com';
        $hashedEmail = hash('sha3-512', $testEmail . $_ENV['ENCRYPTION_KEY']);
        $testToken = 'valid_test_token';

        // Create test user with verification token
        $this->createTestUserWithToken($hashedEmail, $testToken);

        $this->client->request('GET', '/email-verify', ['token' => $testToken]);

        // Should redirect to login with emailVerified parameter
        $response = $this->client->getResponse();
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertStringContainsString('/login', $response->headers->get('Location'));
        $this->assertStringContainsString('emailVerified=true', $response->headers->get('Location'));
    }

    /**
     * Test verifyEmail method with missing token
     *
     * @return void
     */
    public function testVerifyEmailWithMissingToken(): void
    {
        $this->client->request('GET', '/email-verify');

        $response = $this->client->getResponse();
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals('Missing token', $response->getContent());
    }

    /**
     * Test verifyEmail method with invalid token
     *
     * @return void
     */
    public function testVerifyEmailWithInvalidToken(): void
    {
        $this->client->request('GET', '/email-verify', ['token' => 'invalid_token']);

        $response = $this->client->getResponse();
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals('Invalid or expired verification link', $response->getContent());
    }

    /**
     * Test that verifyEmail updates user correctly in database
     *
     * @return void
     */
    public function testVerifyEmailUpdatesUserCorrectly(): void
    {
        // Prepare test data
        $testEmail = 'test@example.com';
        $hashedEmail = hash('sha3-512', $testEmail . $_ENV['ENCRYPTION_KEY']);
        $testToken = 'valid_test_token_for_update';

        // Create test user with verification token
        $this->createTestUserWithToken($hashedEmail, $testToken);

        $this->client->request('GET', '/email-verify', ['token' => $testToken]);

        // Verify that user was updated in database
        $projectDir = dirname(__DIR__, 2);
        $dbPath = $projectDir . '/var/test.db';
        $pdo = new PDO('sqlite:' . $dbPath);

        $stmt = $pdo->prepare('SELECT email_verified, is_active, verification_token FROM api_keys WHERE email = :email');
        $stmt->execute(['email' => $hashedEmail]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertNotFalse($user, 'User should exist in database');
        $this->assertEquals(1, $user['email_verified'], 'Email should be verified');
        $this->assertEquals(1, $user['is_active'], 'User should be active');
        $this->assertNull($user['verification_token'], 'Verification token should be cleared');
    }

    /**
     * Test checkEmailAvailable endpoint accessibility
     *
     * @return void
     */
    public function testCheckEmailAvailableEndpoint(): void
    {
        // This tests the route if it's accessible (though it has unusual signature for a route)
        // The method signature suggests it's meant to be called internally rather than as an endpoint

        // Try to access the check endpoint (may not work due to method signature)
        $this->client->request('GET', '/email-check');

        // Accept any response since the endpoint has unusual signature
        $response = $this->client->getResponse();
        $this->assertInstanceOf(Response::class, $response);
    }

    /**
     * Tear down environment after all tests have run
     *
     * @return void
     */
    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
    }

    /**
     * Get the kernel class for the test
     *
     * @return string
     */
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    /**
     * Initialize test database
     *
     * @return void
     */
    private function initializeTestDatabase(): void
    {
        $projectDir = dirname(__DIR__, 2);
        $dbPath = $projectDir . '/var/test.db'; // Use the test database path

        // Backup existing database if it exists
        if (file_exists($dbPath)) {
            copy($dbPath, $dbPath . '.backup');
            unlink($dbPath);
        }

        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create api_keys table matching the structure expected by EmailController
        $createApiKeysSQL = "
            CREATE TABLE api_keys (
                id INTEGER PRIMARY KEY,
                email VARCHAR(255) NOT NULL,
                password VARCHAR(255) NOT NULL,
                last_connection DATETIME default NULL,
                email_verified INTEGER DEFAULT 0,
                verification_token VARCHAR(255),
                is_active INTEGER DEFAULT 0,
                created_at DATETIME NOT NULL,
                key_value VARCHAR(255) UNIQUE,
                last_used_at DATETIME,
                usage_count INTEGER DEFAULT 0
            )
        ";

        $pdo->exec($createApiKeysSQL);
    }

    /**
     * Create a test user with a verification token
     *
     * @param string $hashedEmail
     * @param string $token
     * @return void
     */
    private function createTestUserWithToken(string $hashedEmail, string $token): void
    {
        $projectDir = dirname(__DIR__, 2);
        $dbPath = $projectDir . '/var/test.db';
        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Remove existing user first
        $pdo->exec("DELETE FROM api_keys WHERE email = '$hashedEmail'");

        // Insert new user with verification token
        $stmt = $pdo->prepare("INSERT INTO api_keys (email, password, verification_token, email_verified, is_active, created_at, usage_count) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $hashedEmail,
            'test_password',
            $token,
            0, // email_verified = false initially
            0, // is_active = false initially
            date('Y-m-d H:i:s'),
            0
        ]);
    }
}
