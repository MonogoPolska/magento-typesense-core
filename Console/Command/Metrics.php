<?php
declare(strict_types=1);

namespace Monogo\TypesenseCore\Console\Command;

use Http\Client\Exception;
use Monogo\TypesenseCore\Adapter\Client;
use Monogo\TypesenseCore\Exceptions\ConnectionException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Typesense\Client as TypesenseClient;
use Typesense\Exceptions\ConfigError;
use Typesense\Exceptions\TypesenseClientError;

class Metrics extends Command
{
    /**
     * @var array|string[]
     */
    protected array $nameMapping = [
        'system_disk_total_bytes' => "Sysytem Total disc space",
        'system_disk_used_bytes' => "System used disc space",
        'system_memory_total_bytes' => "System total memory space",
        'system_memory_used_bytes' => "System used memory space",
        'typesense_memory_active_bytes' => "Server memory active",
        'typesense_memory_allocated_bytes' => "Server memory allocated",
        'typesense_memory_fragmentation_ratio' => "Server memory fragmentation ratio",
        'typesense_memory_mapped_bytes' => "Server memory mapped",
        'typesense_memory_metadata_bytes' => "Server memory metadata",
        'typesense_memory_resident_bytes' => "Server memory resident",
        'typesense_memory_retained_bytes' => "Server memory retained",
    ];
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
        $this->setName('typesense:metrics');
        $this->setDescription('Get Server metrics');

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
        $this->getVersion($client, $output);
        $this->checkHealth($client, $output);
        $this->getMetrics($client, $output);
        return 1;
    }

    /**
     * @param TypesenseClient $client
     * @param OutputInterface $output
     * @return void
     * @throws Exception
     * @throws TypesenseClientError
     */
    protected function getVersion(TypesenseClient $client, OutputInterface $output): void
    {
        $output->write('Server version: ');
        $version = $client->getDebug()->retrieve();
        $output->writeln($version['version']);
    }

    /**
     * @param TypesenseClient $client
     * @param OutputInterface $output
     * @return void
     * @throws Exception
     * @throws TypesenseClientError
     */
    protected function checkHealth(TypesenseClient $client, OutputInterface $output): void
    {
        $output->write('Health status: ');
        $health = $client->getHealth()->retrieve();
        $output->writeln($health['ok']);
    }

    /**
     * @param TypesenseClient $client
     * @param OutputInterface $output
     * @return void
     * @throws Exception
     * @throws TypesenseClientError
     */
    protected function getMetrics(TypesenseClient $client, OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln('Server Metrics');
        $metrics = $client->getMetrics()->retrieve();

        foreach ($metrics as $metricName => $value) {
            if ($metricName != 'typesense_memory_fragmentation_ratio') {
                $value = $this->formatData((int)$value);
            }
            if (isset($this->nameMapping[$metricName])) {
                $metricName = $this->nameMapping[$metricName];
            }
            $output->writeln($metricName . ': ' . $value);

        }
    }

    /**
     * @param $bytes
     * @param $precision
     * @return string
     */
    protected function formatData($bytes, $precision = 2): string
    {
        if ($bytes == 0) {
            return '0B';
        }
        $unit = ["B", "KB", "MB", "GB"];
        $exp = floor(log($bytes, 1024)) | 0;
        return round($bytes / (pow(1024, $exp)), $precision) . $unit[$exp];

    }
}
