<?php
declare(strict_types=1);

namespace Monogo\TypesenseCore\Console\Command;

use Http\Client\Exception;
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
    protected Client $client;

    /**
     * @var ManagerInterface
     */
    private ManagerInterface $eventManager;

    /**
     * @var int
     */
    protected int $takenActions = 0;

    /**
     * @param Client $client
     * @param string|null $name
     */
    public function __construct(Client $client, ManagerInterface $eventManager, string $name = null)
    {
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
        $this->removeAllCollections($client, $output);
        $this->removeAllAliases($client, $output);
        if ($this->takenActions == 0) {
            $output->writeln('All entities are already removed');

        }
        $this->eventManager->dispatch(
            'typesense_after_flush',
            ['output'=>$output]
        );
        return 1;
    }

    /**
     * @param TypesenseClient $client
     * @param OutputInterface $output
     * @return void
     * @throws Exception
     * @throws TypesenseClientError
     */
    protected function removeAllCollections(TypesenseClient $client, OutputInterface $output): void
    {
        $collection = $client->getCollections()->retrieve();
        if (count($collection)) {
            $output->writeln('Removing collections');
        }
        foreach ($collection as $item) {
            $output->writeln($item['name']);
            $client->collections[$item['name']]->delete();
            $this->takenActions++;
        }

    }

    /**
     * @param TypesenseClient $client
     * @param OutputInterface $output
     * @return void
     * @throws Exception
     * @throws TypesenseClientError
     */
    protected function removeAllAliases(TypesenseClient $client, OutputInterface $output): void
    {
        $collection = $client->getAliases()->retrieve();
        if (count($collection['aliases'])) {
            $output->writeln('Removing aliases');
        }
        foreach ($collection['aliases'] as $item) {
            $output->writeln($item['name']);
            $client->aliases[$item['name']]->delete();
            $this->takenActions++;
        }

    }
}
