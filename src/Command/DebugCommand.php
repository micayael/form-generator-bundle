<?php

namespace Micayael\Bundle\FormGeneratorBundle\Command;

use Micayael\Bundle\FormGeneratorBundle\Service\FormGenerator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'micayael:form-generator:debug', description: 'Shows the type aliases and the configuration of the form generator')]
final class DebugCommand extends Command
{
    /**
     * @param array<string, mixed> $config processed configuration of the bundle
     */
    public function __construct(
        private readonly FormGenerator $formGenerator,
        private readonly array $config,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Form generator');

        $rows = [];

        foreach ($this->formGenerator->getTypes() as $alias => $type) {
            $rows[] = [$alias, $type['class'], $this->toJson($type['default_options'])];
        }

        $io->section('Type aliases');
        $io->table(['Alias', 'Form type', 'Default options'], $rows);

        $io->section('Configuration');
        $io->definitionList(
            ['untrusted' => ($this->config['untrusted'] ?? false) ? 'yes' : 'no'],
            ['allowed_classes' => $this->toList($this->config['allowed_classes'] ?? [], 'any form type')],
            ['allowed_options' => $this->toList($this->config['allowed_options'] ?? [])],
            ['allowed_constraints' => $this->toList($this->config['allowed_constraints'] ?? [])],
            ['allowed_html_attributes' => $this->toList($this->config['allowed_html_attributes'] ?? [])],
        );

        return Command::SUCCESS;
    }

    private function toJson(mixed $value): string
    {
        return (string) json_encode($value, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
    }

    private function toList(mixed $values, string $empty = '-'): string
    {
        return \is_array($values) && $values ? implode(', ', $values) : $empty;
    }
}
