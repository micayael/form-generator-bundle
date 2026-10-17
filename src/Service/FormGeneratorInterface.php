<?php

namespace Micayael\Bundle\FormGeneratorBundle\Service;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;

interface FormGeneratorInterface
{
    /**
     * Creates a form builder from an associative array configuration.
     *
     * @param array<string|int, mixed> $formConfig  fields configuration
     * @param array<string, mixed>     $formOptions options of the root form
     */
    public function createFormBuilder(array $formConfig, array $formOptions = [], mixed $data = null, ?string $baseFormTypeClass = null, ?string $groupName = null, ?string $formName = null): FormBuilderInterface;

    /**
     * Creates a form from an associative array configuration.
     *
     * @param array<string|int, mixed> $formConfig  fields configuration
     * @param array<string, mixed>     $formOptions options of the root form
     */
    public function createForm(array $formConfig, array $formOptions = [], mixed $data = null, ?string $baseFormTypeClass = null, ?string $groupName = null, ?string $formName = null): FormInterface;

    /**
     * Creates a form from a JSON configuration.
     *
     * @param array<string, mixed> $formOptions options of the root form
     */
    public function createFormFromJson(string $formConfigJson, array $formOptions = [], mixed $data = null, ?string $baseFormTypeClass = null, ?string $groupName = null, ?string $formName = null): FormInterface;

    /**
     * Creates a form from a YAML configuration.
     *
     * @param array<string, mixed> $formOptions options of the root form
     */
    public function createFormFromYaml(string $formConfigYaml, array $formOptions = [], mixed $data = null, ?string $baseFormTypeClass = null, ?string $groupName = null, ?string $formName = null): FormInterface;

    /**
     * Validates a form configuration without throwing, e.g. before saving it in an admin panel.
     * Uses the same arguments as the createForm*() methods, so the rules of the untrusted mode also apply.
     *
     * @param array<string|int, mixed>|string $formConfig  array, or a YAML/JSON string
     * @param string                          $format      "yaml" or "json", when $formConfig is a string
     * @param array<string, mixed>            $formOptions options of the root form
     *
     * @return array<string, string> error messages by field name ("" for errors of the whole configuration); empty if it is valid
     */
    public function validateConfig(array|string $formConfig, string $format = 'yaml', array $formOptions = [], mixed $data = null, ?string $baseFormTypeClass = null, ?string $groupName = null): array;
}
