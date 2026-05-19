<?php

namespace App\Entity;

use App\Repository\EliquidRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EliquidRepository::class)]
class Eliquid extends Product
{
    #[ORM\Column]
    private ?int $volume = null;

    public function getVolume(): ?int
    {
        return $this->volume;
    }

    public function setVolume(int $volume): static
    {
        $this->volume = $volume;

        return $this;
    }
}
