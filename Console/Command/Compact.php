<?php
declare(strict_types=1);

namespace Console\Command;

use Adapter\Client;
use Exceptions\ConnectionException;
use Http\Client\Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Typesense\Exceptions\ConfigError;
use Typesense\Exceptions\TypesenseClientError;

class Compact extends Command
{
    /**
     * @var Client
     */
    protected Client $client;

    /**
     * @param Client $client
     * @param string|null $name
     */
    public function __construct(Client $client, string $name = null)
    {
        parent::__construct($name);
        $this->client = $client;
    }

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this->setName('typesense:compact');
        $this->setDescription('Compact the on-disk database');

        parent::configure();
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws Exception
     * @throws ConnectionException
     * @throws ConfigError
     * @throws TypesenseClientError
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $client = $this->client->getClient();
        $operation = $client->getOperations()->perform('db/compact');
        $output->writeln('Compacting database: ' . $operation['success']);
        return 1;
    }
}
