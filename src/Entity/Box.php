<?php

namespace App\Entity;

use App\Repository\BoxRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BoxRepository::class)]
class Box extends Product
{
    #[ORM\Column]
    private ?int $typeBattery = null;

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
