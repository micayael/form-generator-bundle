micayael/form-generator-bundle
==============================

[![Symfony 5](https://github.com/micayael/form-generator-bundle/actions/workflows/symfony5.yml/badge.svg)](https://github.com/micayael/form-generator-bundle/actions/workflows/symfony5.yml)
[![Symfony 6](https://github.com/micayael/form-generator-bundle/actions/workflows/symfony6.yml/badge.svg)](https://github.com/micayael/form-generator-bundle/actions/workflows/symfony6.yml)
[![Scrutinizer Quality Score](https://scrutinizer-ci.com/g/micayael/form-generator-bundle/badges/quality-score.png)](https://scrutinizer-ci.com/g/micayael/form-generator-bundle/)
[![Packagist](https://img.shields.io/packagist/v/micayael/form-generator-bundle.svg)](https://packagist.org/packages/micayael/form-generator-bundle)
![License](https://img.shields.io/packagist/l/micayael/form-generator-bundle.svg)
[![Latest Stable Version](https://poser.pugx.org/micayael/form-generator-bundle/v/stable)](https://packagist.org/packages/micayael/form-generator-bundle)
[![Total Downloads](https://poser.pugx.org/micayael/form-generator-bundle/downloads)](https://packagist.org/packages/micayael/form-generator-bundle)
[![PHP from Packagist](https://img.shields.io/packagist/php-v/micayael/form-generator-bundle.svg)](https://packagist.org/packages/micayael/form-generator-bundle)

Introduction
------------

Allows you to generate Symfony forms using YAML, JSON or
associative array configurations based on the standard
configurations of the framework's forms component.

See: https://symfony.com/doc/current/reference/forms/types.html

It is based on the use of the add function of the form
builder used to create forms with Symfony

1. el name para el input de formulario
2. el tipo de campo de formulario
3. un array de options para configurar este tipo de campo

~~~php
// FormInterface::add($child, string $type = null, array $options = [])
$builder->add($inputName, $inputTypeClass, $inputOptions);
~~~

With this it is possible to create a YAML configuration,
JSON or an associative array in which:

- the **key** represents the $inputName
- **type** represents a form $inputTypeClass supported by the
- bundle or the "fully qualified class name (FQN)" of a class form type
- the **options** array represents the form $inputOptions

This allows you to configure forms dynamically by using yaml, json or
an associative array like the examples below:

### YAML

~~~yaml
name:
birthday:
  type: date
  options:
    label: 'Your Birthday'
status:
  type: choice
  options:
    choices:
      Active: A
      Inactive: I
custom_type:
  type: App\Form\Type\CustomType
  options:
    custom_option: value
~~~

### JSON

~~~json
{
  "name": null,
  "birthday": {
    "type": "date",
    "options": {
      "label": "Your Birthday"
    }
  },
  "status": {
    "type": "choice",
    "options": {
      "choices": {
        "Active": "A",
        "Inactive": "I"
      }
    }
  },
  "custom_type": {
    "type": "App\\Form\\Type\\CustomType",
    "options": {
      "custom_option": "value"
    }
  }
}
~~~

### PHP

~~~php
$formConfig = [
  'name' => NULL,
  'birthday' => [
    'type' => 'date',
    'options' => [
      'label' => 'Your Birthday',
    ],
  ],
  'status' => [
    'type' => 'choice',
    'options' => [
      'choices' => [
        'Active' => 'A',
        'Inactive' => 'I',
      ],
    ],
  ],
  'custom_type' => [
    'type' => 'App\\Form\\Type\\CustomType',
    'options' => [
      'custom_option' => 'value',
    ],
  ],
]
~~~

Installation
------------

~~~
composer install micayael/form-generator-bundle
~~~

Usage
-----

Where it is necessary to create a form, for example a controller or
a service, you must inject the FormGenerator object provided by
the bundle.

~~~php
class HomeController extends AbstractController
{
    /** @required */
    public FormGenerator $formGenerator;

    public function __invoke(Request $request): Response
    {
        $formConfigArray = []; // configuration as associative array

        // Gets a FormInterface object with the configured form
        $form = $this->formGenerator->createForm($formConfigArray);

        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()){
            $formData = $form->getData();

            // process your form
        }
    }
~~~

The FormGenerator service provides the following methods:

1. **FormGenerator::createForm(array $formConfig, array $formOptions = [], $data = null):** Creates a form from an associative array
2. **FormGenerator::createFormFromJson(string $formConfigJson, array $formOptions = [], $data = null):** Creates a form from a json string
3. **FormGenerator::createFormFromYaml(string $formConfigYaml, array $formOptions = [], $data = null):** Creates a form from a yaml string

Development
-----------

### Install dependencies

~~~
composer install
~~~

### Testing

~~~
vendor/bin/phpunit
~~~

### Code Review

* phpstan levels: https://phpstan.org/user-guide/rule-levels

~~~
vendor/bin/phpstan analyse src tests --level 5
~~~

* phpmd: https://phpmd.org/download/index.html

~~~
vendor/bin/phpmd ./ text .phpmd-ruleset.xml --exclude var,vendor
~~~