<?php

namespace Micayael\Bundle\FormGeneratorBundle\Tests;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class CommandTest extends KernelTestCase
{
    private const FIXTURES = __DIR__.'/Fixtures/forms';

    protected static function getKernelClass(): string
    {
        return AppTestKernel::class;
    }

    public function testValidateValidFiles(): void
    {
        $tester = $this->createTester('micayael:form-generator:validate');
        $tester->execute(['paths' => [self::FIXTURES.'/valid']]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('contact.yaml', $tester->getDisplay());
        $this->assertStringContainsString('survey.json', $tester->getDisplay());
        $this->assertStringContainsString('2 file(s) are valid', $tester->getDisplay());
    }

    public function testValidateInvalidFiles(): void
    {
        $tester = $this->createTester('micayael:form-generator:validate');
        $tester->execute(['paths' => [self::FIXTURES]]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('broken.yaml', $tester->getDisplay());
        $this->assertStringContainsString('age: Form type "integr" is not supported', $tester->getDisplay());
        $this->assertStringContainsString('1 of 3 file(s) are not valid', $tester->getDisplay());
    }

    public function testValidateWithoutFiles(): void
    {
        $tester = $this->createTester('micayael:form-generator:validate');
        $tester->execute(['paths' => [self::FIXTURES.'/missing']]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('No form configuration files found', $tester->getDisplay());
    }

    public function testDebug(): void
    {
        $tester = $this->createTester('micayael:form-generator:debug');
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('boolean_choice', $tester->getDisplay());
        $this->assertStringContainsString('untrusted', $tester->getDisplay());
    }

    private function createTester(string $command): CommandTester
    {
        $application = new Application(self::bootKernel());

        return new CommandTester($application->find($command));
    }
}
