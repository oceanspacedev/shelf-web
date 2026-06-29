<?php

namespace App\Console;

use Illuminate\Console\Command as IlluminateCommand;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\CommandLoader\CommandLoaderInterface;
use Symfony\Component\Console\Exception\CommandNotFoundException;

class ContainerCommandLoader implements CommandLoaderInterface
{
    protected ContainerInterface $container;

    /**
     * @var array<string, class-string>
     */
    protected array $commandMap;

    public function __construct(ContainerInterface $container, array $commandMap)
    {
        $this->container = $container;
        $this->commandMap = $commandMap;
    }

    public function get(string $name): Command
    {
        if (! $this->has($name)) {
            throw new CommandNotFoundException(sprintf('Command "%s" does not exist.', $name));
        }

        $command = $this->container->get($this->commandMap[$name]);

        if ($command instanceof IlluminateCommand) {
            $command->setLaravel($this->container);
        }

        return $command;
    }

    public function has(string $name): bool
    {
        return $name !== '' && isset($this->commandMap[$name]);
    }

    public function getNames(): array
    {
        return array_keys($this->commandMap);
    }
}
