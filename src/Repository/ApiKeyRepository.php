<?php

namespace App\Repository;

use App\Entity\ApiKey;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository class for ApiKey entity.
 * @extends ServiceEntityRepository<ApiKey>
 *
 * @method ApiKey|null find($id, $lockMode = null, $lockVersion = null)
 * @method ApiKey|null findOneBy(array $criteria, array $orderBy = null)
 * @method ApiKey[]    findAll()
 * @method ApiKey[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ApiKeyRepository extends ServiceEntityRepository
{
    /**
     * Constructor
     *
     * @param ManagerRegistry $registry
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ApiKey::class);
    }

    /**
     * Save an ApiKey entity.
     *
     * @param ApiKey $entity
     * @param bool $flush
     */
    public function save(ApiKey $entity, bool $flush = false): void
    {
        $em = $this->getEntityManager();
        $em->persist($entity);

        if ($flush) {
            $em->flush();
        }
    }

    /**
     * Remove an ApiKey entity.
     *
     * @param ApiKey $entity
     * @param bool $flush
     */
    public function remove(ApiKey $entity, bool $flush = false): void
    {
        $em = $this->getEntityManager();
        $em->remove($entity);

        if ($flush) {
            $em->flush();
        }
    }

    /**
     * Find an active ApiKey by its key value.
     *
     * @param string $keyValue
     * @return ApiKey|null
     */
    public function findActiveByKeyValue(string $keyValue): ?ApiKey
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.keyValue = :keyValue')
            ->andWhere('a.isActive = :isActive')
            ->setParameter('keyValue', $keyValue)
            ->setParameter('isActive', true)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
