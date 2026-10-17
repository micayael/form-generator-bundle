<?php

namespace Micayael\Bundle\FormGeneratorBundle\Exception;

class NotSupportedFormTypeException extends \InvalidArgumentException implements ExceptionInterface
{
    /**
     * @param list<string> $supportedTypes
     */
    public static function unknown(string $inputType, array $supportedTypes): self
    {
        return new self(sprintf(
            'Form type "%s" is not supported. Use one of the aliases "%s" or the FQCN of a form type.',
            $inputType,
            implode('", "', $supportedTypes)
        ));
    }

    public static function notAllowed(string $inputType): self
    {
        return new self(sprintf('Form type "%s" is not in the "allowed_classes" list.', $inputType));
    }
}
