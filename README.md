micayael/form-generator-bundle
==============================

Permite generar formularios de Symfony por medio de configuraciones YAML o JSON basadas
en las configuraciones estándares del componente de fomularios del framework

Ver documentación en: https://symfony.com/doc/current/reference/forms/types.html

Se toma como base el método public function FormInterface::add($child, string $type = null, array $options = []);

~~~php
$builder->add($inputName, $inputTypeClass, $inputOptions);
~~~

Con esto es posible crear una configuración YAML en donde:

- el **key** del campo representa al $inputName
- el **type** representa un $inputTypeClass de input soportado por este bundle que representa a un objeto Type de formulario. Ver clase Micayael\Bundle\FormGeneratorBundle\Config\SupportedTypes.php
- el array **options** representa a los $inputOptions del formulario

~~~yaml
fecha_vencimiento: null
fecha_emision:
    type: date
    options:
        label: 'Fecha de Emisión'
estado:
    type: choice
    options:
        choices:
            Activo: ACTIVO
            Inactivo: INACTIVO
~~~
