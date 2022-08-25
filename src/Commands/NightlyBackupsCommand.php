<?php

namespace OsuWams\Commands;

use AcquiaCloudApi\Endpoints\DatabaseBackups;
use AcquiaCloudApi\Endpoints\Databases;
use Consolidation\OutputFormatters\FormatterManager;
use Consolidation\OutputFormatters\Options\FormatterOptions;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;

class NightlyBackupsCommand extends AcquiaCommand {

  /**
   * @var \AcquiaCloudApi\Endpoints\DatabaseBackups
   */
  protected $databaseBackupAdapter;

  /**
   * @var \AcquiaCloudApi\Endpoints\Databases
   */
  protected $dbAdapter;

  public function __construct() {
    parent::__construct();
    $this->databaseBackupAdapter = new DatabaseBackups($this->client);
    $this->dbAdapter = new Databases($this->client);
  }

  /**
   * Perform a Nightly backup of Cloud Application for the given Environment.
   *
   * @param string $appName
   *   The Acquia Cloud Application name.
   * @param string $env
   *   The environment to operate against.
   *
   * @command backup:nightly
   */
  public function doNightlyBackup($appName, $env, $options = [
    'format' => 'table',
    'fields' => '',
  ]) {
    $appUuId = $this->getUuidFromName($appName);
    $envUuId = $this->getEnvUuIdFromApp($appUuId, $env);
    $dbList = $this->dbAdapter->getAll($appUuId);
    $rows = [];
    /** @var \AcquiaCloudApi\Response\DatabaseResponse $dbName */
    foreach ($dbList as $dbName) {
      $rows[] = ['name' => $dbName->name];
    }
    $opts = new FormatterOptions([], $options);
    $opts->setInput($this->input);
    $opts->setFieldLabels(['name' => 'Database Name']);
    $opts->setDefaultStringField('name');

    $formatterManager = new FormatterManager();
    $formatterManager->write($this->output, $opts->getFormat(), new RowsOfFields($rows), $opts);
  }

}
