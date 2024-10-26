<?php

declare(strict_types=1);

namespace App\Validator;

use App\DTO\EventValidationDTO;
use App\Entity\Event;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class EventValidationDTOSlugUniqueValidator extends ConstraintValidator
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param EventValidationDTOSlugUnique $constraint
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (null === $value || '' === $value) {
            return;
        }

        if (!$value instanceof EventValidationDTO) {
            return;
        }

        $event = $this->entityManager->getRepository(Event::class)->findOneBy(['slug' => $value->getSlug()]);
        if ($event) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $value->getSlug() ?? 'null')
                ->addViolation();
            $this->logger->error("Le slug existe déjà {$value->getSlug()} pour {$event->getTitle()} avec l'id {$event->getId()}");
        }
    }
}
