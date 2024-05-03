<?php

declare(strict_types=1);

namespace App\DTO;

use App\Entity\Event;
use App\Entity\PostalAddress;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Validator\Constraints as Assert;

#[Assert\Expression(
    'this.getStartAt() < this.getEndAt() or !this.getEndAt()',
    message: 'La date de début doit être inférieure à la date de fin'
)]
// #[UniqueEntity(
//     'slug',
//     entityClass: Event::class,
// )]
class EventValidationDTO
{
    #[Assert\NotBlank]
    private ?string $title = null;

    #[Assert\NotBlank]
    private ?string $link = null;

    private ?string $description = null;

    #[Assert\NotBlank]
    private ?\DateTimeImmutable $startAt = null;

    private ?\DateTimeImmutable $endAt = null;

    #[Assert\NotBlank]
    private ?string $organizer = null;

    private ?string $image = null;

    private ?PostalAddress $location = null;

    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[a-z0-9-]+$/', message: 'Le slug ne doit contenir que des lettres minuscules, des chiffres et des tirets')]
    private ?string $slug = null;

    public function toEntity(): Event
    {
        $event = (new Event())
            ->setTitle($this->title)
            ->setLink($this->link)
            ->setDescription($this->description)
            ->setStartAt($this->startAt)
            ->setEndAt($this->endAt)
            ->setOrganizer($this->organizer)
            ->setImage($this->image)
            ->setLocation($this->location)
            ->setSlug($this->slug);

        return $event;
    }

    /**
     * Get the value of title.
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * Set the value of title.
     */
    public function setTitle(?string $title): self
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Get the value of link.
     */
    public function getLink(): ?string
    {
        return $this->link;
    }

    /**
     * Set the value of link.
     */
    public function setLink(?string $link): self
    {
        $this->link = $link;

        return $this;
    }

    /**
     * Get the value of description.
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Set the value of description.
     */
    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Get the value of startAt.
     */
    public function getStartAt(): ?\DateTimeImmutable
    {
        return $this->startAt;
    }

    /**
     * Set the value of startAt.
     */
    public function setStartAt(?\DateTimeImmutable $startAt): self
    {
        $this->startAt = $startAt;

        return $this;
    }

    /**
     * Get the value of endAt.
     */
    public function getEndAt(): ?\DateTimeImmutable
    {
        return $this->endAt;
    }

    /**
     * Set the value of endAt.
     */
    public function setEndAt(?\DateTimeImmutable $endAt): self
    {
        $this->endAt = $endAt;

        return $this;
    }

    /**
     * Get the value of organizer.
     */
    public function getOrganizer(): ?string
    {
        return $this->organizer;
    }

    /**
     * Set the value of organizer.
     */
    public function setOrganizer(?string $organizer): self
    {
        $this->organizer = $organizer;

        return $this;
    }

    /**
     * Get the value of image.
     */
    public function getImage(): ?string
    {
        return $this->image;
    }

    /**
     * Set the value of image.
     */
    public function setImage(?string $image): self
    {
        $this->image = $image;

        return $this;
    }

    /**
     * Get the value of location.
     */
    public function getLocation(): ?PostalAddress
    {
        return $this->location;
    }

    /**
     * Set the value of location.
     */
    public function setLocation(?PostalAddress $location): self
    {
        $this->location = $location;

        return $this;
    }

    /**
     * Get the value of slug.
     */
    public function getSlug(): ?string
    {
        return $this->slug;
    }

    /**
     * Set the value of slug.
     */
    public function setSlug(?string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }

    public function setSlugWithOrganizer(string $slug): static
    {
        $slugger = new AsciiSlugger();
        $slug = $slugger->slug($this->organizer.'-'.$slug)->lower()->toString();
        $this->slug = $slug;

        return $this;
    }
}
