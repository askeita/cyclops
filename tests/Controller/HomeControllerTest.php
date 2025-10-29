<?php

namespace App\Tests\Controller;

use App\Controller\HomeController;
use App\Kernel;
use App\Security\User;
use App\Security\UserProvider;
use Exception;
use PDO;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\ConstraintViolationList;


/**
 * Class HomeControllerTest
 *
 * Tests for HomeController class.
 */
class HomeControllerTest extends WebTestCase
{
    private HomeController $controller;
    private UserProvider|MockObject $userProvider;
    private UserPasswordHasherInterface|MockObject $passwordHasher;
    private CsrfTokenManagerInterface|MockObject $csrfTokenManager;
    private ValidatorInterface|MockObject $validator;
    private KernelBrowser $client;


    /**
     * Sets up the test environment once for all tests.
     *
     * @return void
     */
    public static function setUpBeforeClass(): void
    {
        // Ensure test database directory exists
        $testDbDir = '/var/tmp';
        if (!is_dir($testDbDir)) {
            mkdir($testDbDir, 0755, true);
        }

        // Define explicitly environment variables for tests
        $_ENV['DATABASE_URL'] = 'sqlite:///var/tmp/test.db';
        $_ENV['APP_ENV'] = 'test';
        $_ENV['APP_SECRET'] = 's$cretf0rt3st';
        $_ENV['ENCRYPTION_KEY'] = 'test_encryption_key_for_tests';
        $_ENV['MAILER_DSN'] = 'null://null';
        $_ENV['AWS_REGION'] = 'us-east-1';
        $_ENV['AWS_ACCESS_KEY_ID'] = 'test_key';
        $_ENV['AWS_SECRET_ACCESS_KEY'] = 'test_secret';

        // Ensure variables are also in $_SERVER
        foreach ($_ENV as $key => $value) {
            $_SERVER[$key] = $value;
            putenv("$key=$value");
        }
    }

    /**
     * Sets up the test environment.
     *
     * @return void
     */
    protected function setUp(): void
    {
        // IMPORTANT: Create client first to avoid kernel boot issues
        $this->client = static::createClient();

        // Configure the test database BEFORE creating mocks and controller
        $this->configureTestDatabase();

        $this->userProvider = $this->createMock(UserProvider::class);
        $this->passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $this->csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $this->validator = $this->createMock(ValidatorInterface::class);

        $this->controller = new HomeController(
            $this->userProvider,
            $this->passwordHasher,
            $this->csrfTokenManager,
            $this->validator
        );

        // Mock container for unit tests
        $container = $this->createMock(ContainerInterface::class);
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/dashboard');

        // Configure the container mock
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturnCallback(function($id) use ($urlGenerator) {
            if ($id === 'router') {
                return $urlGenerator;
            }
            if ($id === 'security.csrf.token_manager') {
                return $this->csrfTokenManager;
            }
            return $urlGenerator; // fallback
        });
        $this->controller->setContainer($container);
    }

    /**
     * Configures the test environment to use test database.
     *
     * @return void
     */
    private function configureTestDatabase(): void
    {
        // Create the test database if it doesn't exist
        $projectDir = dirname(__DIR__, 2);
        $testDbPath = $projectDir . '/var/test.db';

        // Ensure the test database directory exists
        $testDbDir = dirname($testDbPath);
        if (!is_dir($testDbDir)) {
            mkdir($testDbDir, 0755, true);
        }

        if (!file_exists($testDbPath)) {
            $pdo = new PDO('sqlite:' . $testDbPath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Create the table if it doesn't exist
            $createTableSQL = "
                CREATE TABLE IF NOT EXISTS api_keys (
                    id INTEGER PRIMARY KEY,
                    email VARCHAR(255) NOT NULL,
                    password VARCHAR(255) NOT NULL,
                    verification_token VARCHAR(255),
                    email_verified INTEGER DEFAULT 0,
                    is_active INTEGER DEFAULT 0,
                    created_at DATETIME NOT NULL,
                    last_used_at DATETIME,
                    usage_count INTEGER DEFAULT 0
                )
            ";
            $pdo->exec($createTableSQL);
        }

        // Create a symlink in the project var directory to test database
        $projectDir = $this->client->getContainer()->getParameter('kernel.project_dir');
        $varDir = $projectDir . '/var';

        // Ensure var directory exists
        if (!is_dir($varDir)) {
            mkdir($varDir, 0755, true);
        }
    }

    /**
     * Tests the CSRF token retrieval.
     *
     * @return void
     */
    public function testGetCsrfToken(): void
    {
        $token = $this->createMock(CsrfToken::class);
        $token->method('getValue')->willReturn('test_token_value');

        $this->csrfTokenManager
            ->expects($this->once())
            ->method('getToken')
            ->with('authenticate')
            ->willReturn($token);

        $response = $this->controller->getCsrfToken();

        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('test_token_value', $responseData['token']);
    }

    /**
     * Tests the API login with an invalid CSRF token.
     *
     * @return void
     */
    public function testApiLoginInvalidCsrfToken(): void
    {
        $request = new Request();
        $request->headers->set('X-CSRF-Token', 'invalid_token');

        $this->csrfTokenManager
            ->expects($this->once())
            ->method('isTokenValid')
            ->willReturn(false);

        try {
            $response = $this->controller->apiLogin($request);
        } catch (Exception $e) {
            throw new RuntimeException($e->getMessage());
        }

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(403, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('Invalid CSRF token', $responseData['error']);
    }

    /**
     * Tests the API login with valid credentials.
     *
     * @return void
     */
    public function testApiLoginSuccessful(): void
    {
        $requestData = [
            'email' => 'test@example.com',
            'password' => 'password123',
            '_token' => 'valid_token'
        ];

        $request = new Request();
        $request->headers->set('X-CSRF-Token', 'valid_token');
        $request->initialize([], [], [], [], [], [], json_encode($requestData));

        $session = $this->createMock(SessionInterface::class);
        $request->setSession($session);

        $user = new User('test@example.com', 'hashed_password', ['ROLE_USER']);

        $this->csrfTokenManager
            ->method('isTokenValid')
            ->willReturn(true);

        $this->validator
            ->method('validate')
            ->willReturn(new ConstraintViolationList());

        $this->userProvider
            ->method('loadUserByIdentifier')
            ->with('test@example.com')
            ->willReturn($user);

        $this->passwordHasher
            ->method('isPasswordValid')
            ->willReturn(true);

        $session->expects($this->once())
            ->method('set')
            ->with('user_email', 'test@example.com');

        try {
            $response = $this->controller->apiLogin($request);
        } catch (Exception $e) {
            throw new RuntimeException($e->getMessage());
        }

        $this->assertInstanceOf(JsonResponse::class, $response);
        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);
    }

    /**
     * Tests the API signup with valid data.
     *
     * @return void
     */
    public function testApiSignupWithValidData(): void
    {
        $requestData = [
            'email' => 'test@example.com',
            'password' => 'password123',
            'confirmPassword' => 'password123',
            '_token' => 'valid_token'
        ];

        $request = new Request();
        $request->headers->set('X-CSRF-Token', 'valid_token');
        $request->initialize([], [], [], [], [], [], json_encode($requestData));

        $user = new User('test@example.com', 'hashed_password', ['ROLE_USER']);

        $this->csrfTokenManager
            ->method('isTokenValid')
            ->willReturn(true);

        $this->validator
            ->method('validate')
            ->willReturn(new ConstraintViolationList());

        // Simulate user doesn't exist: controller expects a UserNotFoundException
        $this->userProvider
            ->method('loadUserByIdentifier')
            ->willThrowException(new UserNotFoundException());

        $this->passwordHasher
            ->method('hashPassword')
            ->willReturn('hashed_password');

        $this->userProvider
            ->method('createUser')
            ->willReturn($user);

        $response = $this->controller->apiSignup($request);
        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);
    }

    /**
     * Tests the API signup with an invalid CSRF token.
     *
     * @return void
     */
    public function testApiSignupWithInvalidCsrfToken(): void
    {
        $request = new Request();
        $request->headers->set('X-CSRF-Token', 'invalid_token');

        $this->csrfTokenManager
            ->method('isTokenValid')
            ->willReturn(false);
        $response = $this->controller->apiSignup($request);

        $this->assertEquals(403, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('Invalid CSRF token', $responseData['error']);
    }

    /**
     * Tests the API signup with password mismatch.
     *
     * @return void
     */
    public function testApiSignupPasswordMismatch(): void
    {
        $requestData = [
            'email' => 'test@example.com',
            'password' => 'password123',
            'confirmPassword' => 'differentPassword',
            '_token' => 'valid_token'
        ];

        $request = new Request();
        $request->headers->set('X-CSRF-Token', 'valid_token');
        $request->initialize([], [], [], [], [], [], json_encode($requestData));

        $this->csrfTokenManager
            ->method('isTokenValid')
            ->willReturn(true);

        $this->validator
            ->method('validate')
            ->willReturn(new ConstraintViolationList());
        $response = $this->controller->apiSignup($request);

        $this->assertEquals(400, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('Passwords do not match', $responseData['error']);
    }

    /**
     * Test login POST with invalid credentials.
     *
     * @return void
     */
    public function testLoginPostWithInvalidCredentials(): void
    {
        // First get a valid CSRF token
        $this->client->request('GET', '/csrf-token');
        $response = $this->client->getResponse();
        $this->assertResponseIsSuccessful();
        $tokenResponse = json_decode($response->getContent(), true);
        $this->assertNotNull($tokenResponse, 'CSRF token response should not be null');
        $this->assertIsArray($tokenResponse, 'CSRF token response should be an array');
        $this->assertArrayHasKey('token', $tokenResponse, 'CSRF token response should contain a token key');
        $this->assertNotNull($tokenResponse['token'], 'CSRF token should not be null');
        $csrfToken = $tokenResponse['token'];

        $data = [
            'email' => 'nonexistent@example.com',
            'password' => 'wrongPassword',
            '_token' => $csrfToken
        ];

        $this->client->request(
            'POST',
            '/api/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_X-CSRF-Token' => $csrfToken],
            json_encode($data)
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Tests login POST with missing data.
     *
     * @return void
     */
    public function testLoginPostWithMissingData(): void
    {
        // First get a valid CSRF token
        $this->client->request('GET', '/csrf-token');
        $response = $this->client->getResponse();
        $this->assertResponseIsSuccessful();
        $tokenResponse = json_decode($response->getContent(), true);
        $this->assertNotNull($tokenResponse, 'CSRF token response should not be null');
        $this->assertIsArray($tokenResponse, 'CSRF token response should be an array');
        $this->assertArrayHasKey('token', $tokenResponse, 'CSRF token response should contain a token key');
        $this->assertNotNull($tokenResponse['token'], 'CSRF token should not be null');
        $csrfToken = $tokenResponse['token'];

        $data = [
            'email' => '',
            'password' => '',
            '_token' => $csrfToken
        ];

        $this->client->request(
            'POST',
            '/api/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_X-CSRF-Token' => $csrfToken],
            json_encode($data)
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    /**
     * Tests email verification with a valid token.
     *
     * @return void
     */
    public function testVerifyEmailWithValidToken(): void
    {
        // First, create a user with verification token
        $this->createTestUserWithToken();

        $this->client->request('GET', '/email-verify?token=testtoken123');

        $this->assertResponseRedirects('/login?emailVerified=true');
    }

    /**
     * Tests email verification with an invalid token.
     *
     * @return void
     */
    public function testVerifyEmailWithInvalidToken(): void
    {
        $this->client->request('GET', '/email-verify?token=invalidtoken');

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    /**
     * Tests email verification with a missing token.
     *
     * @return void
     */
    public function testVerifyEmailWithMissingToken(): void
    {
        $this->client->request('GET', '/email-verify');

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    /**
     * Tests accessing the dashboard without a session.
     *
     * @return void
     */
    public function testDashboardWithoutSession(): void
    {
        $this->client->request('GET', '/dashboard');

        $this->assertResponseRedirects('/login');
    }

    /**
     * Creates a test user with a verification token in the database.
     *
     * @return void
     */
    private function createTestUserWithToken(): void
    {
        // Use the test database path in the project directory
        $projectDir = dirname(__DIR__, 2);
        $testDbPath = $projectDir . '/var/test.db';

        // Ensure the test database directory exists
        $testDbDir = dirname($testDbPath);
        if (!is_dir($testDbDir)) {
            mkdir($testDbDir, 0755, true);
        }

        $pdo = new PDO('sqlite:' . $testDbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create the table if it doesn't exist (for test database)
        $createTableSQL = "
            CREATE TABLE IF NOT EXISTS api_keys (
                id INTEGER PRIMARY KEY,
                email VARCHAR(255) NOT NULL,
                password VARCHAR(255) NOT NULL,
                verification_token VARCHAR(255),
                email_verified INTEGER DEFAULT 0,
                is_active INTEGER DEFAULT 0,
                created_at DATETIME NOT NULL,
                last_used_at DATETIME,
                usage_count INTEGER DEFAULT 0
            )
        ";
        $pdo->exec($createTableSQL);

        $testEmail = 'test235Email205';
        $testToken = 'testtoken123';

        // Delete existing test user first to avoid constraint violations
        $pdo->exec("DELETE FROM api_keys WHERE verification_token = '$testToken' OR email = '$testEmail'");

        $stmt = $pdo->prepare("INSERT INTO api_keys (email, password, verification_token, email_verified, created_at)
                              VALUES (:email, :password, :token, 0, :created_at)");
        $stmt->execute([
            'email' => $testEmail,
            'password' => password_hash('testPassword123', PASSWORD_ARGON2ID),
            'token' => $testToken,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Returns the kernel class for the test.
     *
     * @return string
     */
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    /**
     * Cleans up after tests.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        // Clean up test data from test database
        $projectDir = dirname(__DIR__, 2);
        $testDbPath = $projectDir . '/var/test.db';

        if (file_exists($testDbPath)) {
            $pdo = new PDO('sqlite:' . $testDbPath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->exec("DELETE FROM api_keys WHERE verification_token = 'testtoken123'");
        }

        parent::tearDown();
    }

    /**
     * Clean up after all tests in this class.
     *
     * @return void
     */
    public static function tearDownAfterClass(): void
    {
        // Restore original database if backup exists
        $projectDir = dirname(__DIR__, 2);
        $varDir = $projectDir . '/var';

        // Clean up test database
        $testDbPath = $projectDir . '/var/test.db';
        if (file_exists($testDbPath)) {
            unlink($testDbPath);
        }

        parent::tearDownAfterClass();
    }
}
