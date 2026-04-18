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

    #[ManyToOne(targetEntity: User::class)]
    #[JoinColumn(name: 'user_id', referencedColumnName: 'id')] 
    private User|null $user = null;


    public function getId(): ?int
    {
        return $this->id;
    }
}
