<?php

namespace App\Tests\Repository;

use ApiPlatform\Metadata\Exception\RuntimeException;
use App\Entity\ApiKey;
use App\Kernel;
use App\Tests\Traits\EnsureTestDatabaseTrait;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use ReflectionException;
use ReflectionMethod;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;


/**
 * Class ApiKeyRepositoryTest
 *
 * Tests for the ApiKeyRepository
 */
class ApiKeyRepositoryTest extends KernelTestCase
{
    use EnsureTestDatabaseTrait;

    private ?EntityManagerInterface $em = null;

    /**
     * Define test env vars to ensure DATABASE_URL is available before kernel boot
     *
     * @return void
     */
    public static function setUpBeforeClass(): void
    {
        self::ensureTestDatabaseEnv();
        putenv('MAILER_DSN=null://null'); $_ENV['MAILER_DSN']='null://null'; $_SERVER['MAILER_DSN']='null://null';
    }

    /**
     * Set up the test environment
     *
     * @return void
     */
    protected function setUp(): void
    {
        // Ensure the database tests are not skipped
        if (getenv('SKIP_DB_TESTS')) {
            putenv('SKIP_DB_TESTS');
            unset($_ENV['SKIP_DB_TESTS'], $_SERVER['SKIP_DB_TESTS']);
        }

        self::bootKernel();
        $this->em = self::getContainer()->get('doctrine')->getManager();

        // (Re)create database schema for a clean slate
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $tool = new SchemaTool($this->em);
        if (count($metadata) > 0) {
            $tool->dropSchema($metadata);
            $tool->createSchema($metadata);
        }
    }

    /**
     * Test the findActiveByKeyValue method
     *
     * @return void
     */
    public function testFindActiveByKeyValue(): void
    {
        $repo = $this->em->getRepository(ApiKey::class);

        $activeKey = (new ApiKey())
            ->setKeyValue('key_active')
            ->setIsActive(true)
            ->setEmail('active@example.com');
        $inactiveKey = (new ApiKey())
            ->setKeyValue('key_inactive')
            ->setIsActive(false)
            ->setEmail('inactive@example.com');

        $this->em->persist($activeKey);
        $this->em->persist($inactiveKey);
        $this->em->flush();

        // Custom method from repository
        $found = $repo->createQueryBuilder('a')
            ->andWhere('a.keyValue = :kv')
            ->setParameter('kv', 'key_active')
            ->getQuery()->getOneOrNullResult();
        $this->assertNotNull($found, 'Sanity check: active key exists in DB');

        // Using the repository method under test
        try {
            $method = new ReflectionMethod($repo, 'findActiveByKeyValue');
            $resultActive = $method->invoke($repo, 'key_active');
            $resultInactive = $method->invoke($repo, 'key_inactive');
            $resultUnknown = $method->invoke($repo, 'key_unknown');
        } catch (ReflectionException $e) {
            throw new RuntimeException('Failed to access method', 0, $e);
        }

        $this->assertNotNull($resultActive, 'Active key should be found');
        $this->assertSame('key_active', $resultActive->getKeyValue());
        $this->assertTrue($resultActive->isActive());

        $this->assertNull($resultInactive, 'Inactive key must not be returned');
        $this->assertNull($resultUnknown, 'Unknown key must not be found');
    }

    /**
     * Get the kernel class for the test environment.
     *
     * @return string
     */
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    /**
     * Tear down the test environment
     *
     * @return void
     */
    protected function tearDown(): void
    {
        if ($this->em) {
            $this->em->clear();
            $this->em->close();
            $this->em = null;
        }
        static::ensureKernelShutdown();
        parent::tearDown();
    }
}
