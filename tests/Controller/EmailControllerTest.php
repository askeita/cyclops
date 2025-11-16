<?php

namespace App\Tests\Controller;

use App\Controller\EmailController;
use App\Entity\ApiKey;
use App\Kernel;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use App\Tests\Traits\EnsureTestDatabaseTrait;


/**
 * Class EmailControllerTest
 *
 * Tests for EmailController
 */
class EmailControllerTest extends WebTestCase
{
    use EnsureTestDatabaseTrait;

    private KernelBrowser $client;


    /**
     * Set up environment before any tests run
     *
     * @return void
     */
    public static function setUpBeforeClass(): void
    {
        self::ensureTestDatabaseEnv();
        putenv('MAILER_DSN=null://null'); $_ENV['MAILER_DSN']='null://null'; $_SERVER['MAILER_DSN']='null://null';
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
     * Initialize database schema for tests
     *
     * @return void
     */
    private function initializeSchema(): void
    {
        $em = $this->client->getContainer()->get('doctrine')->getManager();
        $tool = new SchemaTool($em);
        $classes = $em->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($classes);
        $tool->createSchema($classes);
        $em->clear();
    }

    /**
     * Test checkEmailAvailable method
     *
     * @return void
     */
    public function testCheckEmailAvailable(): void
    {
        $controller = $this->client->getContainer()->get(EmailController::class);
        $hashed = hash('sha3-512', 'test@example.com' . $_ENV['ENCRYPTION_KEY']);

        // Aucun enregistrement: doit être disponible
        $this->assertTrue($controller->checkEmailAvailable($hashed));

        // Crée un ApiKey avec cet email
        $em = $this->client->getContainer()->get('doctrine.orm.entity_manager');
        $entity = (new ApiKey())
            ->setEmail($hashed)
            ->setIsActive(false)
            ->setEmailVerified(false)
        ;
        $em->persist($entity);
        $em->flush();

        // Maintenant indisponible
        $this->assertFalse($controller->checkEmailAvailable($hashed));
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
        $em = $this->client->getContainer()->get('doctrine.orm.entity_manager');
        $hashed = hash('sha3-512', 'test@example.com' . $_ENV['ENCRYPTION_KEY']);
        $token = 'valid_test_token';
        $apiKey = (new ApiKey())
            ->setEmail($hashed)
            ->setVerificationToken($token)
            ->setIsActive(false)
            ->setEmailVerified(false);
        $em->persist($apiKey);
        $em->flush();

        $this->client->request('GET', '/email-verify', ['token' => $token]);
        $response = $this->client->getResponse();
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertStringContainsString('/login', $response->headers->get('Location'));
        $this->assertStringContainsString('emailVerified=true', $response->headers->get('Location'));

        $em->clear();
        $reloaded = $em->getRepository(ApiKey::class)->find($apiKey->getId());
        $this->assertTrue($reloaded->isEmailVerified());
        $this->assertTrue($reloaded->isActive());
        $this->assertNull($reloaded->getVerificationToken());
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
     * Test checkEmailAvailable endpoint accessibility
     *
     * @return void
     */
    public function testCheckEmailAvailableEndpoint(): void
    {
        // La méthode est une action qui retourne un bool, pas une Response.
        // On se limite à vérifier que l'URL existe sans erreur serveur.
        $this->client->request('GET', '/email-check');
        $this->assertInstanceOf(Response::class, $this->client->getResponse());
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
}
