<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 */
#[\Attribute()]
class EventValidationDTOSlugUnique extends Constraint
{
    /*
     * Any public properties become valid options for the annotation.
     * Then, use these in your validator class.
     */

    public string $message = 'Le slug "{{ value }}" est déjà utilisé';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
