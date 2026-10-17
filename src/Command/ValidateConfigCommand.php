<?php

namespace Micayael\Bundle\FormGeneratorBundle\Command;

use Micayael\Bundle\FormGeneratorBundle\Service\FormGeneratorInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'micayael:form-generator:validate', description: 'Validates form configuration files (YAML or JSON)')]
final class ValidateConfigCommand extends Command
{
    private const EXTENSIONS = ['yaml', 'yml', 'json'];

    public function __construct(private readonly FormGeneratorInterface $formGenerator)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('paths', InputArgument::IS_ARRAY | InputArgument::REQUIRED, 'Files or directories with .yaml, .yml or .json form configurations')
            ->addOption('base-type', null, InputOption::VALUE_REQUIRED, 'Base form type (FQCN), like the $baseFormTypeClass argument')
            ->addOption('group', null, InputOption::VALUE_REQUIRED, 'Name of the embedded form, like the $groupName argument')
            ->setHelp(<<<'HELP'
                Validates form configurations with the same rules used to create the forms
                (including the untrusted mode, if enabled). Useful in CI:

                  <info>php %command.full_name% config/forms</info>
                  <info>php %command.full_name% product.json --base-type="App\Form\ProductType" --group=attributes</info>
                HELP)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var list<string> $paths */
        $paths = $input->getArgument('paths');
        $files = $this->findFiles($paths, $io);

        if (!$files) {
            $io->error('No form configuration files found.');

            return Command::FAILURE;
        }

        $baseType = $input->getOption('base-type');
        $group = $input->getOption('group');
        $invalidFiles = 0;

        foreach ($files as $file) {
            $format = 'json' === strtolower(pathinfo($file, \PATHINFO_EXTENSION)) ? 'json' : 'yaml';

            $errors = $this->formGenerator->validateConfig(
                (string) file_get_contents($file),
                $format,
                baseFormTypeClass: \is_string($baseType) ? $baseType : null,
                groupName: \is_string($group) ? $group : null,
            );

            if (!$errors) {
                $io->writeln(sprintf('<info>[OK]</info> %s', $file));

                continue;
            }

            ++$invalidFiles;
            $io->writeln(sprintf('<error>[ERROR]</error> %s', $file));

            foreach ($errors as $field => $message) {
                $io->writeln(sprintf('  - %s%s', '' === $field ? '' : $field.': ', $message));
            }
        }

        $io->newLine();

        if ($invalidFiles) {
            $io->error(sprintf('%d of %d file(s) are not valid.', $invalidFiles, \count($files)));

            return Command::FAILURE;
        }

        $io->success(sprintf('%d file(s) are valid.', \count($files)));

        return Command::SUCCESS;
    }

    /**
     * @param list<string> $paths
     *
     * @return list<string>
     */
    private function findFiles(array $paths, SymfonyStyle $io): array
    {
        $files = [];

        foreach ($paths as $path) {
            if (is_file($path)) {
                $files[] = $path;

                continue;
            }

            if (!is_dir($path)) {
                $io->warning(sprintf('The path "%s" does not exist.', $path));

                continue;
            }

            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS));

            foreach ($iterator as $file) {
                if ($file instanceof \SplFileInfo && \in_array(strtolower($file->getExtension()), self::EXTENSIONS, true)) {
                    $files[] = $file->getPathname();
                }
            }
        }

        sort($files);

        return array_values(array_unique($files));
    }
}
