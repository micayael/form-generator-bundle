<?php

namespace Micayael\Bundle\FormGeneratorBundle\Tests\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

class FixedFieldsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('fixed1')
            ->add('fixed2')
        ;
    }
}
