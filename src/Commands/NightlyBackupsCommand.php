<?php

namespace OsuWams\Commands;

use AcquiaCloudApi\Endpoints\DatabaseBackups;
use AcquiaCloudApi\Endpoints\Databases;
use DateTime;

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
   * @param string $dbBackupDir
   *   The full path to the backup directory.
   *
   * @throws \Exception
   * @command backup:nightly
   */
  public function doNightlyBackup($appName, $env, $dbBackupDir = '/tmp') {
    $backupDay = new DateTime('now');
    $backupDay = $backupDay->format('Ymd');
    $appShortName = explode(":", $appName)[1];
    $appUuId = $this->getUuidFromName($appName);
    $envUuId = $this->getEnvUuIdFromApp($appUuId, $env);
    $databaseList = $this->dbAdapter->getAll($appUuId);
    /** @var \AcquiaCloudApi\Response\DatabaseResponse $databaseResponse */
    foreach ($databaseList as $databaseResponse) {
      $databaseName = $databaseResponse->name;
      $allBackups = $this->databaseBackupAdapter->getAll($envUuId, $databaseName);
      $dailyBackups = array_filter($allBackups->getArrayCopy(), function ($backup) {
        return $backup->type === "daily";
      });
      // Sort the backup date/time to ensure we have the latest at position 1.
      usort($dailyBackups, function ($a, $b) {
        return $a->completedAt < $b->completedAt;
      });
      // Get the latest backup id.
      $backupId = $dailyBackups[0]->id;
      $dbBackupPath = "${dbBackupDir}/daily/${backupDay}/${appShortName}/${env}/${databaseName}.sql.gz";
      $this->say("Copying down back for ${databaseName} to ${dbBackupPath}");
      file_put_contents($dbBackupPath, $this->databaseBackupAdapter->download($envUuId, $databaseName, $backupId));
    }

  }

}
