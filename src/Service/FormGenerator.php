<?php

namespace Micayael\Bundle\FormGeneratorBundle\Service;

use Micayael\Bundle\FormGeneratorBundle\Config\SupportedTypes;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\String\UnicodeString;
use Symfony\Component\Validator\Constraints\Date;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Url;
use Symfony\Component\Yaml\Yaml;

class FormGenerator
{
    /** @required */
    public FormFactoryInterface $formFactory;

    public function createFormFromYaml(string $formConfigYaml, array $formOptions = [], $data = null): ?FormInterface
    {
        $array = Yaml::parse($formConfigYaml);
        $json = json_encode($array);

        return $this->createFormFromJson($json, $formOptions, $data);
    }

    public function createFormFromJson(string $formConfigJson, array $formOptions = [], $data = null): ?FormInterface
    {
        $formConfig = json_decode($formConfigJson, true);

        return $this->createForm($formConfig, $formOptions, $data);
    }

    public function createForm(array $formConfig, array $formOptions = [], $data = null): ?FormInterface
    {
        if (empty($formConfig)) {
            return null;
        }

        $form = $this->formFactory->create(FormType::class, $data, $formOptions);

        foreach ($formConfig as $inputName => $inputConfig) {
            $inputType = isset($inputConfig['type']) ? $inputConfig['type'] : 'text';

            // Se obtiene la configuración predeterminada
            $inputDefaultConfig = SupportedTypes::getDefaultTypeConfig($inputType);

            // se obtienen las opciones definidas para este input
            $inputOptions = isset($inputConfig['options']) ? $inputConfig['options'] : [];

            $inputOptions['constraints'] = $this->getConstraints($inputType, $inputDefaultConfig['default_options'], $inputOptions);

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

    private function getConstraints(string $inputType, array $inputDefaultOptions, array $inputOptions)
    {
        $ret = [];

        $validations = isset($inputDefaultOptions['constraints']) ? $inputDefaultOptions['constraints'] : [];
        $validations = array_merge($validations, isset($inputOptions['constraints']) ? $inputOptions['constraints'] : []);

        foreach ($validations as $validation => $validationOptions) {

            switch ($validation){
                case 'not_blank':
                    if(isset($inputOptions['required']) && false === $inputOptions['required']){
                        // no se setea la validación
                    }else{
                        $ret[] = new NotBlank($validationOptions);
                    }
                    break;
                case 'email':
                    $ret[] = new Email($validationOptions);
                    break;
                case 'url':
                    $ret[] = new Url($validationOptions);
                    break;
                case 'length':
                    $ret[] = new Length($validationOptions);
                    break;
                case 'date':
                    $ret[] = new Date($validationOptions);
                    break;
            }

        }

        return $ret;

    }
}
