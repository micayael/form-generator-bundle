# Changelog

## 2.0.0 (unreleased)

### Requirements

- PHP >= 8.1 (PHP 7.4 / 8.0 stay on the 1.x branch).
- Symfony 5.4, 6.4, 7.4 and 8.x.

### Fixed

- Fields without `extras.group` were wrapped in an unmapped `default_default` sub-form, so their
  data was lost from `$form->getData()`.
- The `type`, `time` and `datetime` default constraints were silently ignored.
- Invalid JSON/YAML produced a `TypeError` instead of a clear exception.
- Removed the non-existent `TestService` from the service definitions.
- Abstract form types or constraints throw the bundle exceptions instead of a PHP `Error`.

### Added

- Any Symfony constraint can be used by its snake_case name (`not_blank`, `length`, `regex`, ...)
  or FQCN, as a list of names, or as `Constraint` objects. `name: false` disables a default constraint.
  A constraint defined twice is created once.
- Nested constraints (`all`, `at_least_one_of`, `sequentially`, `when`, `collection`, ...).
- Constraints are created with named arguments, compatible with Symfony 8.
- Bundle configuration: `types` (custom aliases or overrides of the built-in ones) and
  `allowed_classes` (restrict which FQCN form types can be used).
- `FormGeneratorInterface` service alias, `createFormBuilder()` method and `$formName` argument.
- Validation of the field configuration (unknown keys, wrong types) with `InvalidConfigurationException`.
- Untrusted mode (`untrusted`, `allowed_options`, `allowed_constraints`) for configurations written by users:
  blocks dangerous options (`property_path`, `data_class`, `label_html`, ...), the `expression`, `when` and
  `callback` constraints, non-Symfony constraints, FQCN types not in `allowed_classes` and fields mapped to objects.
- All bundle exceptions implement `Micayael\Bundle\FormGeneratorBundle\Exception\ExceptionInterface`.

### Changed (BC breaks)

- The default options of the built-in aliases are now applied (in 1.x they never were): fields with an
  alias get `not_blank` unless `required: false`, and `date` / `time` / `datetime` use `widget: single_text`
  and `input: string`, so their data is a string instead of a `\DateTime`. Forms that validated before may fail now.
- With `$groupName`, the `extras.group` / `extras.position` groups are created inside the embedded form
  (they were ignored before).
- Callables in the configuration (`normalizer`, `callback`, array callables in `validation_groups`,
  `choice_label`, ...) are only accepted as `\Closure`.
- A field and a group with the same name throw an exception (the field used to replace the group).
- `createForm*()` always return a `FormInterface`; an empty configuration returns an empty form instead of `null`.
- Grouped fields (`extras.group` / `extras.position`) use `inherit_data`, so their data stays in the parent form.
  The group label no longer includes the internal key.
- Fields with a FQCN `type` only get the `constraints` option if constraints are defined.
- Unknown constraints throw an exception instead of being ignored.
- `NotSupportedFormTypeException` extends `\InvalidArgumentException` and is created with
  `NotSupportedFormTypeException::unknown()` / `::notAllowed()`. Messages are in English.
- `FormGenerator` and `SupportedTypes` are `final`; `SupportedTypes::getDefaultTypeConfig()` and
  `getSupportedClass()` were removed; `FormGenerator::$formFactory` is private.
