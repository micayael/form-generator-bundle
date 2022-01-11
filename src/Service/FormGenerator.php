<?php

namespace Micayael\Bundle\FormGeneratorBundle\Service;

use Micayael\Bundle\FormGeneratorBundle\Config\SupportedTypes;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Url;
use Symfony\Component\Yaml\Yaml;

class FormGenerator
{
    /** @required */
    public FormFactoryInterface $formFactory;

    public function createFormFromYaml(string $formConfigYaml): ?FormInterface
    {
        $array = Yaml::parse($formConfigYaml);
        $json = json_encode($array);

        return $this->createFormFromJson($json);
    }

    public function createFormFromJson(string $formConfigJson): ?FormInterface
    {
        $formConfig = json_decode($formConfigJson, true);

        return $this->createForm($formConfig);
    }

    public function createForm(array $formConfig): ?FormInterface
    {
        if (empty($formConfig)) {
            return null;
        }

        $form = $this->formFactory->create();

        foreach ($formConfig as $inputName => $inputConfig) {
            $inputType = isset($inputConfig['type']) ? $inputConfig['type'] : 'text';

            // Se obtiene la configuración predeterminada
            $inputDefaultConfig = SupportedTypes::getDefaultTypeConfig($inputType);

            // se obtienen las opciones definidas para este input
            $inputOptions = isset($inputConfig['options']) ? $inputConfig['options'] : [];

            // se agregan validaciones
            if ('email' === $inputType) {
                $inputOptions['constraints'][] = new Email();
            } elseif ('url' === $inputType) {
                $inputOptions['constraints'][] = new Url();
            }

            // Se compilan las opciones default con las definidas
            $inputOptions = array_merge(
                $inputDefaultConfig['default_options'],
                $inputOptions
            );

            // Se crea el input de formulario
            $form->add($inputName, $inputDefaultConfig['class'], $inputOptions);
        }

        return $form;
    }
}
