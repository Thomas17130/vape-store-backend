<?php

namespace App\Entity;

use App\Repository\OrderRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: '`order`')]
class Order
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[ApiResource]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $dateOfCreation = null;

    #[ORM\Column]
    private ?int $numberOrder = null;

    #[OneToMany(targetEntity: OrderLine::class, mappedBy: 'order')]    
    private Collection $orderLines;

    #[ManyToOne(targetEntity: User::class)]
    #[JoinColumn(name: 'user_id', referencedColumnName: 'id')] 
    private User|null $user = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDateOfCreation(): ?\DateTime
    {
        return $this->dateOfCreation;
    }

    public function setDateOfCreation(\DateTime $dateOfCreation): static
    {
        $this->dateOfCreation = $dateOfCreation;

        return $this;
    }

    public function getNumberOrder(): ?int
    {
        return $this->numberOrder;
    }

    public function setNumberOrder(int $numberOrder): static
    {
        $this->numberOrder = $numberOrder;

        return $this;
    }
}
