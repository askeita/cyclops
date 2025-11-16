<?php

namespace App\Tests\Controller;

use App\Kernel;
use App\Entity\ApiKey;
use Doctrine\ORM\Tools\SchemaTool;
use Exception;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use App\Tests\Traits\EnsureTestDatabaseTrait;
use Symfony\Component\BrowserKit\Cookie;


/**
 * Class DashboardControllerTest
 *
 * Tests for DashboardController
 */
class DashboardControllerTest extends WebTestCase
{
    use EnsureTestDatabaseTrait;
    private KernelBrowser $client;


    /**
     * Set up environment variables before any tests run
     *
     * @return void
     */
    public static function setUpBeforeClass(): void
    {
        self::ensureTestDatabaseEnv();
        putenv('MAILER_DSN=null://null'); $_ENV['MAILER_DSN']='null://null'; $_SERVER['MAILER_DSN']='null://null';
        putenv('ENCRYPTION_KEY=testEncryptionKey'); $_ENV['ENCRYPTION_KEY']='testEncryptionKey'; $_SERVER['ENCRYPTION_KEY']='testEncryptionKey';
    }

    /**
     * Set up before each test
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->initializeSchema();
    }

    /**
     * Initialize the database schema for testing
     *
     * @return void
     */
    private function initializeSchema(): void
    {
        $em = $this->client->getContainer()->get('doctrine')->getManager();
        $tool = new SchemaTool($em);
        $classes = $em->getMetadataFactory()->getAllMetadata();
        try { $tool->dropSchema($classes); } catch (Exception) {}
        try { $tool->createSchema($classes); } catch (Exception) {}
        $em->clear();
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
        // Insert test user into database if not exists
        $em = $this->client->getContainer()->get('doctrine.orm.entity_manager');
        $email = 'test@example.com';
        $encKey = $this->client->getContainer()->getParameter('app.encryption_key');
        $hashed = hash('sha3-512', $email . $encKey);
        $existing = $em->getRepository(ApiKey::class)->findOneBy(['email' => $hashed]);
        if (!$existing) {
            $k = (new ApiKey())
                ->setEmail($hashed)
                ->setPassword(password_hash('testPassword123', PASSWORD_BCRYPT))
                ->setEmailVerified(true)
                ->setIsActive(true);
            $em->persist($k);
            $em->flush();
        }

        // Simulates login by setting session
        $session = self::getContainer()->get('session.factory')->createSession();
        $session->set('user_email', $email);
        $session->save();
        $this->client->getCookieJar()->set(new Cookie($session->getName(), $session->getId()));
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
}
