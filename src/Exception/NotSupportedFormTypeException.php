<?php

namespace Micayael\Bundle\FormGeneratorBundle\Exception;

use Micayael\Bundle\FormGeneratorBundle\Config\SupportedTypes;

class NotSupportedFormTypeException extends \Exception
{
    public function __construct(string $inputType)
    {
        $message = sprintf(
            'Tipo de formulario "%s" no soportado. Tipos soportados: %s',
            $inputType,
            implode(',', array_keys(SupportedTypes::SUPPORTED_TYPES))
        );

        parent::__construct($message);
    }
}
