micayael/form-generator-bundle
==============================

[![CI](https://github.com/micayael/form-generator-bundle/actions/workflows/ci.yml/badge.svg)](https://github.com/micayael/form-generator-bundle/actions/workflows/ci.yml)
[![Latest Stable Version](https://img.shields.io/packagist/v/micayael/form-generator-bundle.svg)](https://packagist.org/packages/micayael/form-generator-bundle)
[![Total Downloads](https://img.shields.io/packagist/dt/micayael/form-generator-bundle.svg)](https://packagist.org/packages/micayael/form-generator-bundle)
[![PHP from Packagist](https://img.shields.io/packagist/php-v/micayael/form-generator-bundle.svg)](https://packagist.org/packages/micayael/form-generator-bundle)
![License](https://img.shields.io/packagist/l/micayael/form-generator-bundle.svg)

Generate Symfony forms from YAML, JSON or PHP array configurations, using the
standard options of the [Symfony form types](https://symfony.com/doc/current/reference/forms/types.html).
Useful for dynamic forms stored in a database or in configuration files: surveys, product attributes,
custom fields per customer, etc.

- [Compatibility](#compatibility)
- [Installation](#installation)
- [Configuration format](#configuration-format)
- [Usage](#usage)
- [Methods and arguments](#methods-and-arguments)
- [Built-in types](#built-in-types)
- [Constraints](#constraints)
- [Groups](#groups)
- [Bundle configuration](#bundle-configuration)
- [Using it without the bundle](#using-it-without-the-bundle)
- [Error handling](#error-handling)
- [Security](#security)
- [Upgrading from 1.x](#upgrading-from-1x)
- [Development](#development)

Compatibility
-------------

| Version | PHP    | Symfony                |
|---------|--------|------------------------|
| 2.x     | >= 8.1 | 5.4, 6.4, 7.4, 8.x     |
| 1.x     | >= 7.4 | 5.1 – 7.x              |

Installation
------------

~~~
composer require micayael/form-generator-bundle
~~~

With Symfony Flex the bundle is registered automatically. Otherwise, add it to `config/bundles.php`:

~~~php
return [
    // ...
    Micayael\Bundle\FormGeneratorBundle\MicayaelFormGeneratorBundle::class => ['all' => true],
];
~~~

Configuration format
--------------------

Each field of the configuration maps to a call to the form builder:

~~~php
$builder->add($inputName, $inputTypeClass, $inputOptions);
~~~

| Key       | Description                                                                                  |
|-----------|----------------------------------------------------------------------------------------------|
| (the key) | the `$inputName`                                                                             |
| `type`    | a [built-in alias](#built-in-types) or the FQCN of a form type. Default: `text`              |
| `options` | the `$inputOptions`, merged over the defaults of the alias                                   |
| `extras`  | optional. `group` and `position` render the field inside a section (see [Groups](#groups))   |

Any other key throws an exception. A field without configuration (`name:` in YAML, `null` in JSON/PHP)
is a required `text` field.

The same form in the three formats:

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
    'name' => null,
    'birthday' => [
        'type' => 'date',
        'options' => ['label' => 'Your Birthday'],
    ],
    'status' => [
        'type' => 'choice',
        'options' => [
            'choices' => ['Active' => 'A', 'Inactive' => 'I'],
        ],
    ],
    'custom_type' => [
        'type' => CustomType::class,
        'options' => ['custom_option' => 'value'],
    ],
];
~~~

Usage
-----

Inject `FormGeneratorInterface` (or get the public `micayael.form_generator` service).
In this example the form configuration is stored as YAML in a `Survey` entity:

~~~php
use Micayael\Bundle\FormGeneratorBundle\Service\FormGeneratorInterface;

class SurveyController extends AbstractController
{
    public function __construct(private readonly FormGeneratorInterface $formGenerator)
    {
    }

    #[Route('/survey/{id}', name: 'survey')]
    public function __invoke(Survey $survey, Request $request): Response
    {
        $form = $this->formGenerator->createFormFromYaml($survey->getFormConfig());

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $answers = $form->getData(); // ['name' => 'Jhon Doe', 'birthday' => '1990-05-20', ...]

            // save the answers...

            return $this->redirectToRoute('survey_thanks');
        }

        return $this->render('survey/show.html.twig', [
            'form' => $form,
        ]);
    }
}
~~~

~~~twig
{# templates/survey/show.html.twig #}
{{ form_start(form) }}
    {{ form_widget(form) }}
    <button type="submit">Send</button>
{{ form_end(form) }}
~~~

The generated form doesn't include a submit button, so add it in the template (or with
[`createFormBuilder()`](#createformbuilder)).

Methods and arguments
---------------------

| Method                                        | Returns                |
|-----------------------------------------------|------------------------|
| `createForm(array $formConfig, ...)`          | `FormInterface`        |
| `createFormFromJson(string $formConfigJson, ...)` | `FormInterface`    |
| `createFormFromYaml(string $formConfigYaml, ...)` | `FormInterface`    |
| `createFormBuilder(array $formConfig, ...)`   | `FormBuilderInterface` |

All of them accept the same optional arguments. Named arguments are recommended:

| Argument             | Description                                                                         |
|----------------------|-------------------------------------------------------------------------------------|
| `$formOptions`       | options of the root form                                                            |
| `$data`              | initial data of the form (array or object)                                          |
| `$baseFormTypeClass` | form type (FQCN) used as the base to add the fields. Default: `FormType`            |
| `$groupName`         | if set, the fields are added inside an embedded form with this name                 |
| `$formName`          | name of the root form. Default: the block prefix of the base form type (`form`)     |

An empty configuration returns an empty form (the base form type without extra fields).

### createForm

From a PHP array:

~~~php
$form = $this->formGenerator->createForm([
    'name' => null,
    'email' => ['type' => 'email'],
]);
~~~

### createFormFromJson

From a JSON string, e.g. a JSON column of the database or the body of an API request:

~~~php
$form = $this->formGenerator->createFormFromJson($product->getAttributesFormJson());
~~~

### createFormFromYaml

From a YAML string, e.g. a file of your project:

~~~php
$form = $this->formGenerator->createFormFromYaml(
    file_get_contents($this->getParameter('kernel.project_dir').'/config/forms/contact.yaml')
);
~~~

### createFormBuilder

Returns the builder, to keep adding fields, listeners or a submit button before creating the form:

~~~php
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

$builder = $this->formGenerator->createFormBuilder($formConfig);

$builder
    ->add('accept_terms', CheckboxType::class, ['mapped' => false])
    ->add('save', SubmitType::class)
    ->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event): void {
        // ...
    })
;

$form = $builder->getForm();
~~~

### $formOptions and $data

`$formOptions` configures the root form, and `$data` fills it with initial values:

~~~php
$form = $this->formGenerator->createForm(
    $formConfig,
    formOptions: ['attr' => ['novalidate' => 'novalidate'], 'validation_groups' => ['Default']],
    data: ['name' => 'Jhon Doe', 'birthday' => '1990-05-20'],
);
~~~

### $baseFormTypeClass and $groupName

Add dynamic fields to an existing form type. With `$groupName` they are grouped in an embedded form,
so they don't mix with the fixed fields:

~~~php
// ProductType has the fixed fields: name, price
$form = $this->formGenerator->createForm(
    $category->getAttributesFormConfig(), // e.g. ['color' => null, 'size' => ['type' => 'choice', ...]]
    data: $productData,
    baseFormTypeClass: ProductType::class,
    groupName: 'attributes',
);

$form->getData();
// [
//     'name' => 'T-shirt',
//     'price' => 10,
//     'attributes' => ['color' => 'red', 'size' => 'M'],
// ]
~~~

If the base form type uses a `data_class`, the object needs an `attributes` property (e.g. an array
or JSON column).

### $formName

Sets the name of the root form, which is the prefix of the HTML field names (`contact[email]`).
Useful to render several generated forms in the same page:

~~~php
$contactForm = $this->formGenerator->createForm($contactConfig, formName: 'contact');
$surveyForm = $this->formGenerator->createForm($surveyConfig, formName: 'survey');
~~~

Built-in types
--------------

| Alias            | Form type      | Default constraints / options                         |
|------------------|----------------|-------------------------------------------------------|
| `text`           | TextType       | `not_blank`                                           |
| `textarea`       | TextareaType   | `not_blank`                                           |
| `email`          | EmailType      | `not_blank`, `email`                                  |
| `password`       | PasswordType   | `not_blank`                                           |
| `url`            | UrlType        | `not_blank`, `url`                                    |
| `tel`            | TelType        | `not_blank`                                           |
| `uuid`           | UuidType       | `not_blank`                                           |
| `integer`        | IntegerType    | `not_blank`, `type: integer`                          |
| `number`         | NumberType     | `not_blank`, `type: numeric`                          |
| `money`          | MoneyType      | `not_blank`, `type: numeric`                          |
| `percent`        | PercentType    | `not_blank`, `type: numeric`                          |
| `choice`         | ChoiceType     | `not_blank`                                           |
| `country`        | CountryType    | `not_blank`                                           |
| `language`       | LanguageType   | `not_blank`                                           |
| `locale`         | LocaleType     | `not_blank`                                           |
| `timezone`       | TimezoneType   | `not_blank`                                           |
| `currency`       | CurrencyType   | `not_blank`                                           |
| `date`           | DateType       | `not_blank`, `date`; `widget: single_text`, `input: string` |
| `time`           | TimeType       | `not_blank`, `time`; `widget: single_text`, `input: string`, `with_seconds: false` |
| `datetime`       | DateTimeType   | `not_blank`, `datetime`; `widget: single_text`, `input: string`, `with_seconds: false` |
| `boolean`        | CheckboxType   | `type: boolean`; `required: false`                    |
| `boolean_choice` | ChoiceType     | `type: boolean`; `required: false`, choices `Si`/`No` |

Your options are merged over the defaults:

~~~yaml
nickname:
  type: text
  options:
    required: false   # also removes the default not_blank constraint
    label: Nickname
~~~

When a FQCN is used as `type`, no defaults are applied: the form type is used as it is.

The date types work with strings (`Y-m-d`, `H:i:s`, `Y-m-d H:i:s`) by default. To get date objects,
change the `input` and disable the default constraint, since it validates a string:

~~~yaml
birthday:
  type: date
  options:
    input: datetime_immutable
    constraints:
      date: false
~~~

To add your own aliases or change the defaults, see [Bundle configuration](#bundle-configuration).

Constraints
-----------

The `constraints` option accepts any [Symfony constraint](https://symfony.com/doc/current/reference/constraints.html)
by its snake_case name (`not_blank`, `length`, `regex`, `greater_than`, ...) or its FQCN, with its options
as a map:

~~~yaml
code:
  options:
    constraints:
      length: { min: 3, max: 10 }
      regex: { pattern: '/^[A-Z]+$/' }
age:
  type: integer
  options:
    constraints:
      range: { min: 18, max: 99 }
~~~

Constraints without options can be listed by name:

~~~yaml
email:
  type: text
  options:
    constraints: [not_blank, email]
~~~

`name: false` disables a default constraint of the alias:

~~~yaml
email:
  type: email
  options:
    constraints:
      not_blank: false   # optional, but must be a valid email if filled
~~~

In PHP you can also use `Constraint` objects:

~~~php
use Symfony\Component\Validator\Constraints\Length;

$form = $this->formGenerator->createForm([
    'code' => ['options' => ['constraints' => ['not_blank', new Length(max: 10)]]],
]);
~~~

A constraint defined twice (by name, alias or FQCN) is created once, with the last options.
`required: false` removes the `not_blank` constraint, whichever way it was defined.

### Nested constraints

Constraints that contain other constraints (`all`, `at_least_one_of`, `sequentially`, `when`,
`collection`, `required`, `optional`, ...) are resolved too:

~~~yaml
tags:
  type: Symfony\Component\Form\Extension\Core\Type\CollectionType
  options:
    allow_add: true
    constraints:
      all:
        constraints:
          length: { max: 20 }

contact:
  type: App\Form\Type\ContactType
  options:
    constraints:
      collection:
        fields:
          email: [not_blank, email]
          phone:
            optional:
              constraints:
                length: { max: 15 }
~~~

Groups
------

Fields with `extras.group` and/or `extras.position` are added inside a sub-form, to render them as
sections in the template. Their data stays in the parent form (they use `inherit_data`), so
`$form->getData()` doesn't change.

~~~yaml
first_name:
  extras:
    group: Personal data
    position: left
last_name:
  extras:
    group: Personal data
    position: left
phone:
  extras:
    group: Contact
    position: right
notes:
  type: textarea
~~~

- The key of the group is the slug of `group_position`: `personal_data_left`, `contact_right`.
  Without `position`, it is `default` (`personal_data_default`).
- The label of the group is the `group` value. With only `position`, the group is `default`
  (key `default_left`) and has no label.
- Fields without `extras` are added directly to the form (`notes`).
- A field can't have the same name as a group: it throws an exception.

~~~php
$form['personal_data_left']['first_name']; // the field
$form->getData();                          // ['first_name' => ..., 'last_name' => ..., 'phone' => ..., 'notes' => ...]
~~~

Rendering the sections in two columns:

~~~twig
{{ form_start(form) }}
    <div class="row">
        <div class="col">
            {{ form_row(form.personal_data_left) }}
        </div>
        <div class="col">
            {{ form_row(form.contact_right) }}
        </div>
    </div>

    {{ form_rest(form) }}

    <button type="submit">Send</button>
{{ form_end(form) }}
~~~

Bundle configuration
--------------------

All the configuration is optional:

~~~yaml
# config/packages/micayael_form_generator.yaml
micayael_form_generator:
  # custom aliases, or overrides of the built-in ones
  types:
    # override: only the default_options you set are replaced
    boolean_choice:
      default_options:
        choices: { 'Yes': true, 'No': false }

    # new alias, usable as "type: product_category"
    product_category:
      class: App\Form\Type\ProductCategoryType
      default_options:
        required: false
        constraints:
          not_blank: false

  # when the configuration comes from users or the database, restrict the FQCN usable in "type"
  # (the aliases are always allowed). Empty: any form type is allowed
  allowed_classes:
    - App\Form\Type\ProductCategoryType
    - Symfony\Component\Form\Extension\Core\Type\CollectionType
~~~

Using it without the bundle
---------------------------

`FormGenerator` only needs a form factory, so it also works without the bundle (in tests, scripts
or apps using the standalone Form component). The other arguments are the same `types` and
`allowed_classes` of the bundle configuration:

~~~php
use Micayael\Bundle\FormGeneratorBundle\Service\FormGenerator;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Forms;
use Symfony\Component\Validator\Validation;

$formFactory = Forms::createFormFactoryBuilder()
    ->addExtension(new ValidatorExtension(Validation::createValidator()))
    ->getFormFactory();

$formGenerator = new FormGenerator(
    $formFactory,
    types: ['boolean_choice' => ['default_options' => ['choices' => ['Yes' => true, 'No' => false]]]],
    allowedClasses: [],
);

$form = $formGenerator->createFormFromYaml($yaml);
~~~

Error handling
--------------

Invalid configurations throw an exception implementing
`Micayael\Bundle\FormGeneratorBundle\Exception\ExceptionInterface`:

| Exception                         | When                                                                          |
|-----------------------------------|-------------------------------------------------------------------------------|
| `InvalidConfigurationException`   | invalid JSON/YAML, unknown keys in a field, unknown constraints or options, callables not given as `\Closure`, field and group with the same name |
| `NotSupportedFormTypeException`   | `type` is not an alias nor a form type class, or it is not in `allowed_classes` |

Both extend `\InvalidArgumentException`. The messages explain the problem, e.g.
`Unknown key(s) "typ" in the configuration of the field "name". Allowed keys: "type", "options", "extras".`

~~~php
use Micayael\Bundle\FormGeneratorBundle\Exception\ExceptionInterface;

try {
    $form = $this->formGenerator->createFormFromJson($survey->getFormConfig());
} catch (ExceptionInterface $e) {
    $this->logger->error('Invalid survey form', ['survey' => $survey->getId(), 'error' => $e->getMessage()]);

    throw $this->createNotFoundException();
}
~~~

To validate a configuration when it is saved (e.g. in an admin panel), create the form and catch the exception.

Security
--------

`options` are passed to the form types as they are. To avoid running arbitrary code from a
configuration stored in YAML/JSON, callables are only accepted as `\Closure` (that is, from PHP code):

- constraint options typed as callables (`normalizer`, `callback`, ...)
- form options that Symfony calls when they receive an array callable (`validation_groups`, `choice_label`,
  `choice_value`, `group_by`, `preferred_choices`, ...) and `getter` / `setter`

~~~yaml
# throws an exception: "normalizer" only accepts a \Closure
name:
  options:
    constraints:
      not_blank: { normalizer: trim }
~~~

Even so, if the configuration comes from untrusted users, also restrict `allowed_classes` and review which
options they can set (`attr`, `label_html`, `help_html`, ... affect the rendered HTML).

Upgrading from 1.x
------------------

Version 2.0 requires PHP 8.1 and fixes bugs that changed the behavior, so review your forms:

- The defaults of the aliases are applied now (in 1.x they never were): fields get `not_blank` unless
  `required: false`, and `date` / `time` / `datetime` return strings instead of `\DateTime`.
- Fields are no longer wrapped in a `default_default` sub-form; their data is in `$form->getData()`.
- `createForm*()` never return `null`.
- Unknown keys, constraints or options throw an exception instead of being ignored.
- Callables in the configuration must be `\Closure`.

See the [CHANGELOG](CHANGELOG.md) for the full list.

Development
-----------

~~~
composer install
composer test
composer phpstan
composer cs
vendor/bin/phpmd src text .phpmd-ruleset.xml
~~~

License
-------

MIT. See [LICENSE](LICENSE).
