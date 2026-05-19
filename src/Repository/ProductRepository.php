<?php

namespace App\Repository;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    /**
     * @return Product[]
     */
    public function findByFilters(?string $search, ?string $type, ?int $brandId): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.brand', 'b')
            ->addSelect('b')
            ->orderBy('p.id', 'DESC');

        if ($search !== null && $search !== '') {
            $qb->andWhere('LOWER(p.name) LIKE :search OR LOWER(p.description) LIKE :search')
                ->setParameter('search', '%'.mb_strtolower($search).'%');
        }

        if ($type !== null && $type !== '') {
            if ($type === 'box') {
                $qb->andWhere('p INSTANCE OF App\Entity\Box');
            } elseif ($type === 'e-liquid' || $type === 'eliquid') {
                $qb->andWhere('p INSTANCE OF App\Entity\Eliquid');
            } elseif ($type === 'product') {
                $qb->andWhere('p NOT INSTANCE OF App\Entity\Box')
                    ->andWhere('p NOT INSTANCE OF App\Entity\Eliquid');
            }
        }

        if ($brandId !== null) {
            $qb->andWhere('b.id = :brandId')->setParameter('brandId', $brandId);
        }

        return $qb->getQuery()->getResult();
    }

    //    /**
    //     * @return Product[] Returns an array of Product objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('p.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Product
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
