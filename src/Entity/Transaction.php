<?php

namespace App\Entity;

use App\Repository\TransactionRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=TransactionRepository::class)
 */
class Transaction
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity=User::class, inversedBy="transactions")
     * @ORM\JoinColumn(nullable=false)
     */
    private $owner;

    /**
     * @ORM\Column(type="datetime")
     */
    private $created_at;


    /**
     * @ORM\Column(type="string", length=255)
     */
    private $trs_id;

    /**
     * @ORM\OneToOne(targetEntity=Order::class, mappedBy="transaction", cascade={"persist", "remove"})
     */
    private $ar_order;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): self
    {
        $this->owner = $owner;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->created_at;
    }

    public function setCreatedAt(\DateTimeInterface $created_at): self
    {
        $this->created_at = $created_at;

        return $this;
    }

    public function getTrsId(): ?string
    {
        return $this->trs_id;
    }

    public function setTrsId(string $trs_id): self
    {
        $this->trs_id = $trs_id;

        return $this;
    }

    public function getArOrder(): ?Order
    {
        return $this->ar_order;
    }

    public function setArOrder(?Order $ar_order): self
    {
        // unset the owning side of the relation if necessary
        if ($ar_order === null && $this->ar_order !== null) {
            $this->ar_order->setTransaction(null);
        }

        // set the owning side of the relation if necessary
        if ($ar_order !== null && $ar_order->getTransaction() !== $this) {
            $ar_order->setTransaction($this);
        }

        $this->ar_order = $ar_order;

        return $this;
    }
}
