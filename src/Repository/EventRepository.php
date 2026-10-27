<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Event;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Event>
 *
 * @method Event|null find(mixed $id, int|null $lockMode = null, int|null $lockVersion = null)
 * @method Event|null findOneBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null)
 * @method Event[]    findAll()
 * @method Event[]    findBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null, int|null $limit = null, int|null $offset = null)
 */
class EventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Event::class);
    }

    /**
     * Retourne un QueryBuilder pour lister les événements ordonnés (utilisé pour la pagination).
     */
    public function createListQueryBuilder(): \Doctrine\ORM\QueryBuilder
    {
        return $this->createQueryBuilder('e')
            ->orderBy('e.startAt', \SortDirection::Descending);
    }

    /**
     * @param array<int>|null $source
     *
     * @return array<Event>
     */
    public function findLatest(
        ?array $source = null,
    ): array {
        $qb = $this->createQueryBuilder('e')
        ->where('e.startAt >= :now')
        ->setParameter('now', new \DateTime())
        ->andWhere('e.published IS NOT NULL')
        ->orderBy('e.startAt', \SortDirection::Ascending)
        ;

        if (null !== $source && [] !== $source) {
            $sourceList = [
                1 => 'cobaltpoitiers',
                2 => 'pwn',
                3 => 'emf',
                4 => 'afup-poitiers',
                5 => 'poitiers-aws-user-group',
            ];

            $source = array_values(array_filter(array_map(
                static function ($id) use ($sourceList) {
                    return $sourceList[$id] ?? null;
                },
                $source
            )));

            if ([] !== $source) {
                $qb
                    ->andWhere('e.source IN (:source)')
                    ->setParameter('source', $source)
                ;
            }
        }

        /** @var Event[] $result */
        $result = $qb
            ->getQuery()
            ->getResult();

        return $result;
    }
}
