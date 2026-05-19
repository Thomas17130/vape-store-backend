<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
class User
{
    public const ROLE_ADMIN = 'ROLE_ADMIN';
    public const ROLE_USER = 'ROLE_USER';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $email = null;

    #[ORM\Column(length: 255)]
    private ?string $address = null;

    #[ORM\Column(length: 255)]
    private ?string $passwordHash = null;

    #[ORM\Column(length: 50, options: ['default' => self::ROLE_USER])]
    private string $role = self::ROLE_USER;

    #[ORM\Column(length: 80, nullable: true, unique: true)]
    private ?string $authToken = null;

    #[ORM\OneToMany(targetEntity: Order::class, mappedBy: 'user')]
    private Collection $orders;

    #[ORM\OneToMany(targetEntity: Cartline::class, mappedBy: 'user')]
    private Collection $cartLines;

    #[ORM\OneToMany(targetEntity: CustomerAddress::class, mappedBy: 'user')]
    private Collection $addresses;

    #[ORM\OneToMany(targetEntity: StoreOrder::class, mappedBy: 'user')]
    private Collection $storeOrders;

    public function __construct()
    {
        $this->orders = new ArrayCollection();
        $this->cartLines = new ArrayCollection();
        $this->addresses = new ArrayCollection();
        $this->storeOrders = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(string $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function getOrders(): Collection
    {
        return $this->orders;
    }

    public function getCartLines(): Collection
    {
        return $this->cartLines;
    }

    public function getAddresses(): Collection
    {
        return $this->addresses;
    }

    public function getStoreOrders(): Collection
    {
        return $this->storeOrders;
    }

    public function getPasswordHash(): ?string
    {
        return $this->passwordHash;
    }

    public function setPasswordHash(string $passwordHash): static
    {
        $this->passwordHash = $passwordHash;

        return $this;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): static
    {
        $normalized = strtoupper(trim($role));
        $this->role = $normalized === self::ROLE_ADMIN ? self::ROLE_ADMIN : self::ROLE_USER;

        return $this;
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function getAuthToken(): ?string
    {
        return $this->authToken;
    }

    public function setAuthToken(?string $authToken): static
    {
        $this->authToken = $authToken;

        return $this;
    }
}
