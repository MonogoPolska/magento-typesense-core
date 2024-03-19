<?php
declare(strict_types=1);

namespace Monogo\TypesenseCore\Console\Command;

use Http\Client\Exception;
use Magento\Framework\Console\Cli;
use Magento\Framework\Event\ManagerInterface;
use Monogo\TypesenseCore\Adapter\Client;
use Monogo\TypesenseCore\Exceptions\ConnectionException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Typesense\Client as TypesenseClient;
use Typesense\Exceptions\ConfigError;
use Typesense\Exceptions\TypesenseClientError;

class Flush extends Command
{
    /**
     * @var Client
     */
    private Client $client;

    /**
     * @var ManagerInterface
     */
    private ManagerInterface $eventManager;

    /**
     * @param Client           $client
     * @param ManagerInterface $eventManager
     * @param string|null      $name
     */
    public function __construct(
        Client $client,
        ManagerInterface $eventManager,
        string $name = null
    ) {
        parent::__construct($name);

        $this->client = $client;
        $this->eventManager = $eventManager;
    }

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this->setName('typesense:flush');
        $this->setDescription('Flush all imported data in Typesense');

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
        $this->eventManager->dispatch(
            'typesense_before_flush',
            ['output'=>$output]
        );

        $client = $this->client->getClient();

        $numberOfRemovedEntities = $this->removeAllCollections($client, $output)
            + $this->removeAllAliases($client, $output);

        if ($numberOfRemovedEntities == 0) {
            $output->writeln('All entities are already removed.');
        }

        $this->eventManager->dispatch(
            'typesense_after_flush',
            ['output'=>$output]
        );

        return Cli::RETURN_SUCCESS;
    }

    /**
     * @param TypesenseClient $client
     * @param OutputInterface $output
     *
     * @return int
     *
     * @throws Exception
     * @throws TypesenseClientError
     */
    private function removeAllCollections(TypesenseClient $client, OutputInterface $output): int
    {
        $collection = $client->getCollections()->retrieve();

        if (count($collection)) {
            $output->writeln('Removing collections');
        }

        $removedCollectionsCount = 0;

        foreach ($collection as $item) {
            $output->writeln($item['name']);
            $client->collections[$item['name']]->delete();
            $removedCollectionsCount++;
        }

        return $removedCollectionsCount;
    }

    /**
     * @param TypesenseClient $client
     * @param OutputInterface $output
     *
     * @return int
     *
     * @throws Exception
     * @throws TypesenseClientError
     */
    private function removeAllAliases(TypesenseClient $client, OutputInterface $output): int
    {
        $collection = $client->getAliases()->retrieve();

        if (count($collection['aliases'])) {
            $output->writeln('Removing aliases');
        }

        $removedAliasesCount = 0;

        foreach ($collection['aliases'] as $item) {
            $output->writeln($item['name']);
            $client->aliases[$item['name']]->delete();
            $removedAliasesCount++;
        }

        return $removedAliasesCount;
    }
}
