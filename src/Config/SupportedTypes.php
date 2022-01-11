<?php

namespace Micayael\Bundle\FormGeneratorBundle\Config;

use Micayael\Bundle\FormGeneratorBundle\Exception\NotSupportedFormTypeException;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\CurrencyType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\LanguageType;
use Symfony\Component\Form\Extension\Core\Type\LocaleType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\PercentType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\Extension\Core\Type\TimezoneType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;

class SupportedTypes
{
    public const SUPPORTED_TYPES = [
        // Texts
        'text' => [
            'class' => TextType::class,
            'default_options' => [],
        ],
        'textarea' => [
            'class' => TextareaType::class,
            'default_options' => [],
        ],
        'email' => [
            'class' => EmailType::class,
            'default_options' => [],
        ],
        'password' => [
            'class' => PasswordType::class,
            'default_options' => [],
        ],
        'url' => [
            'class' => UrlType::class,
            'default_options' => [],
        ],
        'tel' => [
            'class' => TelType::class,
            'default_options' => [],
        ],

        // Numbers
        'integer' => [
            'class' => IntegerType::class,
            'default_options' => [],
        ],
        'money' => [
            'class' => MoneyType::class,
            'default_options' => [],
        ],
        'number' => [
            'class' => NumberType::class,
            'default_options' => [],
        ],
        'percent' => [
            'class' => PercentType::class,
            'default_options' => [],
        ],

        // Choices
        'choice' => [
            'class' => ChoiceType::class,
            'default_options' => [],
        ],
        'country' => [
            'class' => CountryType::class,
            'default_options' => [],
        ],
        'language' => [
            'class' => LanguageType::class,
            'default_options' => [],
        ],
        'locale' => [
            'class' => LocaleType::class,
            'default_options' => [],
        ],
        'timezone' => [
            'class' => TimezoneType::class,
            'default_options' => [],
        ],
        'currency' => [
            'class' => CurrencyType::class,
            'default_options' => [],
        ],

        // Dates
        'date' => [
            'class' => DateType::class,
            'default_options' => [
//                'html5' => false,
                'input' => 'string',
                'widget' => 'single_text',
//                'format' => 'dd/MM/yyyy',
//                'attr' => [
//                    'placeholder' => 'dd/mm/aaaa',
//                ]
            ],
        ],
        'time' => [
            'class' => TimeType::class,
            'default_options' => [
                'input' => 'string',
                'widget' => 'single_text',
                'with_seconds' => false,
            ],
        ],

        // Special types
        'boolean' => [
            'class' => CheckboxType::class,
            'default_options' => [
                'required' => false,
            ],
        ],
    ];

    public static function getDefaultTypeConfig(string $inputType): array
    {
        if (!isset(self::SUPPORTED_TYPES[$inputType])) {
            throw new NotSupportedFormTypeException($inputType);
        }

        return self::SUPPORTED_TYPES[$inputType];
    }
}
