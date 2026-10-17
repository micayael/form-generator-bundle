<?php

namespace Micayael\Bundle\FormGeneratorBundle\Tests\Validator;

use Symfony\Component\Validator\Constraint;

class AppConstraint extends Constraint
{
    public function validatedBy(): string
    {
        return AppConstraintValidator::class;
    }
}
