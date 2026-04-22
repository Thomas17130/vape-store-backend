<?php

namespace App\Entity;

use App\Repository\CartlineRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CartlineRepository::class)]
class Cartline
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[ApiResource]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id')] 
    private User|null $user = null;

    #[ORM\ManyToOne(targetEntity: Product::class)]    
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id')]    
    private Product|null $product = null;

    #[ORM\Column]
    private ?int $quantity = null;

    public function getId(): ?int
    {
        return $this->id;
    }
}
