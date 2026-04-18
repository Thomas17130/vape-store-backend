<?php

namespace App\Entity;

use App\Repository\BoxRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BoxRepository::class)]
class Box extends Product
{
//    #[ORM\Id]
//    #[ORM\GeneratedValue]
//    #[ORM\Column]
    //private ?int $id = null;

    #[ORM\Column]
    #[ApiResource]
    private ?int $typeBattery = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTypeBattery(): ?int
    {
        return $this->typeBattery;
    }

    public function setTypeBattery(int $typeBattery): static
    {
        $this->typeBattery = $typeBattery;

        return $this;
    }
}
