<?php

namespace App\Entity;

use App\Repository\CategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=CategoryRepository::class)
 */
class Category
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $en_name;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $fa_name;

    /**
     * @ORM\ManyToMany(targetEntity=Course::class, inversedBy="categories")
     */
    private $courses;

    public function __construct()
    {
        $this->courses = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEnName(): ?string
    {
        return $this->en_name;
    }

    public function setEnName(string $en_name): self
    {
        $this->en_name = $en_name;

        return $this;
    }

    public function getFaName(): ?string
    {
        return $this->fa_name;
    }

    public function setFaName(string $fa_name): self
    {
        $this->fa_name = $fa_name;

        return $this;
    }

    /**
     * @return Collection|Course[]
     */
    public function getCourses(): Collection
    {
        return $this->courses;
    }

    public function addCourse(Course $course): self
    {
        if (!$this->courses->contains($course)) {
            $this->courses[] = $course;
        }

        return $this;
    }

    public function removeCourse(Course $course): self
    {
        $this->courses->removeElement($course);

        return $this;
    }
}
