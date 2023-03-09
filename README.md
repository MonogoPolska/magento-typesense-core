# Core module for Typesense Magento integration

Module should contain only generic and core functionalities. If you want to add new indexers, please create a new module

## Configuration
As the first step, Go to Magento Admin -> Configuration -> Typesense

### General section
Configure the connection parameters for Typesense instance 

### Advanced section
Configure the additional parameters

### Indexing Queue / Cron section
Configure Queue/Cron for indexers


## CLI

| Command                              | Description                                      |
|--------------------------------------|--------------------------------------------------|
| ```bin/magento typesense:compact```  | Compact the on-disk database of Typesense server |
| ```bin/magento typesense:flush```    | Flush all imported data in Typesense             |
| ```bin/magento typesense:metrics```  | Get Typesense server metrics                     |


## Indexers

| Indexer                                                  | Description                                                                                                                     |
|----------------------------------------------------------|---------------------------------------------------------------------------------------------------------------------------------|
| ```bin/magento indexer:reindex typesense_queue_runner``` | Typesense Queue Runner. To enable this, configure <br/>Stores &rarr; Configuration &rarr;Typesense &rarr; Indexing Queue / Cron |
| ```bin/magento indexer:reindex typesense_all```          | Metaindexer. It will runn all dependent indexes defined in modules                                                              |


# Credits
- [Monogo](https://monogo.pl/en)
- [Typesense](https://typesense.org)
- [Official Algolia magento module](https://github.com/algolia/algoliasearch-magento-2)
