<?php

namespace Micayael\Bundle\FormGeneratorBundle\Tests;

use Micayael\Bundle\FormGeneratorBundle\MicayaelFormGeneratorBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class AppTestKernel extends BaseKernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new MicayaelFormGeneratorBundle(),
        ];
    }

    // Cache separada por versión de Symfony para poder alternar versiones localmente
    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/micayael_form_generator_bundle/'.BaseKernel::VERSION.'/cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/micayael_form_generator_bundle/'.BaseKernel::VERSION.'/log';
    }

    protected function configureContainer(ContainerConfigurator $c): void
    {
        $c->extension('framework', [
            'secret' => 'devsecret',
            'test' => true,
            'http_method_override' => false,
            'csrf_protection' => false,
            // En Symfony 5.x no definirlo genera una deprecación (desde 6.0 es true por defecto)
            'router' => [
                'utf8' => true,
            ],
            'validation' => [
                'email_validation_mode' => 'html5',
            ],
        ]);
    }
}
