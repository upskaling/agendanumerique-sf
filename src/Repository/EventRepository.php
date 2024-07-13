<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Event;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Event>
 *
 * @method Event|null find($id, $lockMode = null, $lockVersion = null)
 * @method Event|null findOneBy(array $criteria, array $orderBy = null)
 * @method Event[]    findAll()
 * @method Event[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Event::class);
    }

    /**
     * @param array<int> $selection
     *
     * @return array<Event>
     */
    public function findLatest(
        ?array $selection
    ): array {
        $qb = $this->createQueryBuilder('e')
        ->where('e.startAt >= :now')
        ->setParameter('now', new \DateTime())
        ->andWhere('e.published IS NOT NULL')
        ->orderBy('e.startAt', 'ASC')
        ;

        if (null !== $selection) {
            $qb
            ->andWhere('e.location IN (:selection)')
            ->setParameter('selection', $selection)
            ;
        }

        /** @var Event[] $result */
        $result = $qb
            ->getQuery()
            ->getResult();

        return $result;
    }
}
