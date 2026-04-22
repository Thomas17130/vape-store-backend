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

    private ?int $id = null;
    
    #[ORM\ManyToOne(targetEntity: Product::class)]    
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id')]    
    private Product|null $product = null;

    #[ORM\Column]
    private ?int $quantity = null; 

    #[ORM\ManyToOne(targetEntity: Order::class)]    
    #[ORM\JoinColumn(name: 'order_id', referencedColumnName: 'id')]    
    private Order|null $order = null;
    
    public function getId(): ?int
    {
        return $this->id;
    }
}
