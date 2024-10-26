<?php

declare(strict_types=1);

namespace App\DTO;

use App\Entity\Event;
use App\Entity\PostalAddress;
use App\Validator\EventValidationDTOSlugUnique;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Validator\Constraints as Assert;

#[Assert\Expression(
    'this.getStartAt() < this.getEndAt() or !this.getEndAt()',
    message: 'La date de début doit être inférieure à la date de fin'
)]
#[EventValidationDTOSlugUnique()]
class EventValidationDTO
{
    #[Assert\NotBlank]
    private string $title;

    #[Assert\NotBlank]
    private string $link;

    private ?string $description = null;

    #[Assert\NotBlank]
    private ?\DateTimeImmutable $startAt = null;

    private ?\DateTimeImmutable $endAt = null;

    #[Assert\NotBlank]
    private string $organizer;

    #[Assert\Url(requireTld: true)]
    private ?string $image = null;

    private ?PostalAddress $location = null;

    #[Assert\Regex(pattern: '/^[a-z0-9-]+$/', message: 'Le slug ne doit contenir que des lettres minuscules, des chiffres et des tirets')]
    private ?string $slug = null;

    public function __construct(
        #[Assert\NotBlank]
        public string $source,
    ) {
        $this->startAt = new \DateTimeImmutable();
    }

    public function toEntity(): Event
    {
        $event = new Event();

        if (isset($this->title)
            && $title = $this->title) {
            $event->setTitle($title);
            $slugger = new AsciiSlugger();
            $this->slug = $slugger->slug($this->organizer.'-'.$title)->lower()->toString();
            $event->setSlug($this->slug);
        }

        if (isset($this->link)) {
            $event->setLink($this->link);
        }

        if (isset($this->description)) {
            $event->setDescription($this->description);
        }

        if (null !== $startAt = $this->startAt) {
            $event->setStartAt($startAt);
        }

        $event->setEndAt($this->endAt);

        if (isset($this->organizer)) {
            $event->setOrganizer($this->organizer);
        }

        if (isset($this->image)) {
            $event->setImage($this->image);
        }
        $event->setLocation($this->location);

        if (isset($this->slug)) {
            $event->setSlug($this->slug);
        }

        if (isset($this->source)) {
            $event->setSource($this->source);
        }

        return $event;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getLink(): ?string
    {
        return $this->link;
    }

    public function setLink(string $link): self
    {
        $this->link = $link;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getStartAt(): ?\DateTimeImmutable
    {
        return $this->startAt;
    }

    public function setStartAt(?\DateTimeImmutable $startAt): self
    {
        $this->startAt = $startAt;

        return $this;
    }

    public function getEndAt(): ?\DateTimeImmutable
    {
        return $this->endAt;
    }

    public function setEndAt(?\DateTimeImmutable $endAt): self
    {
        $this->endAt = $endAt;

        return $this;
    }

    public function getOrganizer(): ?string
    {
        return $this->organizer;
    }

    public function setOrganizer(string $organizer): self
    {
        $this->organizer = $organizer;

        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): self
    {
        $this->image = $image;

        return $this;
    }

    public function getLocation(): ?PostalAddress
    {
        return $this->location;
    }

    public function setLocation(?PostalAddress $location): self
    {
        $this->location = $location;

        return $this;
    }

    public function getSlug(): ?string
    {
        if (isset($this->slug)) {
            return $this->slug;
        }
        $slugger = new AsciiSlugger();
        $this->slug = $slugger->slug($this->organizer.'-'.$this->title)->lower()->toString();

        return $this->slug;
    }

    public function setSlug(?string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }
}
