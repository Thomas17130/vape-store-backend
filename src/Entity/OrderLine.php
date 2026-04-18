<?php

namespace App\Entity;

use App\Repository\OrderLineRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrderLineRepository::class)]
class OrderLine
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[ApiResource]
    private ?int $id = null;
    
    #[ManyToOne(targetEntity: Product::class)]    
    #[JoinColumn(name: 'product_id', referencedColumnName: 'id')]    
    private Product|null $product = null;

    #[ManyToOne(targetEntity: Order::class)]    
    #[JoinColumn(name: 'order_id', referencedColumnName: 'id')]    
    private Order|null $order = null;
    
    public function getId(): ?int
    {
        return $this->id;
    }
}
