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
            'default_options' => [
                'constraints' => [
                    'not_blank' => [],
                ],
            ],
        ],
        'textarea' => [
            'class' => TextareaType::class,
            'default_options' => [
                'constraints' => [
                    'not_blank' => [],
                ],
            ],
        ],
        'email' => [
            'class' => EmailType::class,
            'default_options' => [
                'constraints' => [
                    'not_blank' => [],
                    'email' => []
                ],
            ],
        ],
        'password' => [
            'class' => PasswordType::class,
            'default_options' => [
                'constraints' => [
                    'not_blank' => [],
                ],
            ],
        ],
        'url' => [
            'class' => UrlType::class,
            'default_options' => [
                'constraints' => [
                    'not_blank' => [],
                    'url' => [],
                ],
            ],
        ],
        'tel' => [
            'class' => TelType::class,
            'default_options' => [
                'constraints' => [
                    'not_blank' => [],
                ],
            ],
        ],

        // Numbers
        'integer' => [
            'class' => IntegerType::class,
            'default_options' => [
                'constraints' => [
                    'not_blank' => [],
                    'type' => [
                        'type' => 'integer',
                    ],
                ],
            ],
        ],
        'money' => [
            'class' => MoneyType::class,
            'default_options' => [
                'constraints' => [
                    'not_blank' => [],
                    'type' => [
                        'type' => 'numeric',
                    ],
                ],
            ],
        ],
        'number' => [
            'class' => NumberType::class,
            'default_options' => [
                'constraints' => [
                    'not_blank' => [],
                    'type' => [
                        'type' => 'numeric',
                    ],
                ],
            ],
        ],
        'percent' => [
            'class' => PercentType::class,
            'default_options' => [
                'constraints' => [
                    'not_blank' => [],
                    'type' => [
                        'type' => 'numeric',
                    ],
                ],
            ],
        ],

        // Choices
        'choice' => [
            'class' => ChoiceType::class,
            'default_options' => [
                'constraints' => [
                    'not_blank' => [],
                ],
            ],
        ],
        'country' => [
            'class' => CountryType::class,
            'default_options' => [
                'constraints' => [
                    'not_blank' => [],
                ],
            ],
        ],
        'language' => [
            'class' => LanguageType::class,
            'default_options' => [
                'constraints' => [
                    'not_blank' => [],
                ],
            ],
        ],
        'locale' => [
            'class' => LocaleType::class,
            'default_options' => [
                'constraints' => [
                    'not_blank' => [],
                ],
            ],
        ],
        'timezone' => [
            'class' => TimezoneType::class,
            'default_options' => [
                'constraints' => [
                    'not_blank' => [],
                ],
            ],
        ],
        'currency' => [
            'class' => CurrencyType::class,
            'default_options' => [
                'constraints' => [
                    'not_blank' => [],
                ],
            ],
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
                'constraints' => [
                    'not_blank' => [],
                    'date' => [
                        'message' => 'chau'
                    ],
                ],
            ],
        ],
        'time' => [
            'class' => TimeType::class,
            'default_options' => [
                'input' => 'string',
                'widget' => 'single_text',
                'with_seconds' => false,
                'constraints' => [
                    'not_blank' => [],
                    'time' => [],
                ],
            ],
        ],

        // Special types
        'boolean' => [
            'class' => CheckboxType::class,
            'default_options' => [
                'required' => false,
                'constraints' => [
                    'type' => [
                        'type' => 'boolean',
                        'message' => 'The value {{ value }} is not a valid {{ type }}.',
                    ],
                ],
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
