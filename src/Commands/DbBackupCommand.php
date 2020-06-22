<?php


namespace OsuWams\Commands;


use AcquiaCloudApi\Endpoints\DatabaseBackups;
use DateTime;
use DateTimeZone;
use Exception;
use Symfony\Component\Console\Helper\Table;

class DbBackupCommand extends AcquiaCommand {

  /**
   * @var \AcquiaCloudApi\Endpoints\DatabaseBackups
   */
  protected $databaseBackupAdapter;

  public function __construct() {
    parent::__construct();
    $this->databaseBackupAdapter = new DatabaseBackups($this->client);
  }

  /**
   * Create a database backup in the environment.
   *
   * @param string $appName
   * @param string $env
   * @param string $dbName
   *
   * @throws \Exception
   * @command db:backup:create
   */
  public function backupDbCreate($appName, $env, $dbName) {
    $appUuId = $this->getUuidFromName($appName);
    $envUuId = $this->getEnvUuIdFromApp($appUuId, $env);
    $this->createDbBackup($dbName, $envUuId);
    $this->say('Backup started');
  }

  /**
   * Delete a Database backup.
   *
   * @param $appName
   * @param $env
   * @param $dbName
   * @param $backupId
   *
   * @command db:backup:delete
   * @throws \Exception
   */
  public function deleteBackupDb($appName, $env, $dbName, $backupId) {
    $appUuId = $this->getUuidFromName($appName);
    $envUuId = $this->getEnvUuIdFromApp($appUuId, $env);
    $this->databaseBackupAdapter->delete($envUuId, $dbName, $backupId);
  }

  /**
   * List all backups for a given database.
   *
   * @param string $appName
   *  The Acquia CLoud Application Name.
   * @param string $env
   *  The Environment short name.
   * @param string $dbName
   *  The Database name, usually snake case.
   *
   * @throws \Exception
   * @command db:backup:list
   */
  public function listBackupDbs($appName, $env, $dbName) {
    $appUuId = $this->getUuidFromName($appName);
    $envUuId = $this->getEnvUuIdFromApp($appUuId, $env);
    $backups = $this->databaseBackupAdapter->getAll($envUuId, $dbName);
    $output = $this->output();
    $table = new Table($output);
    $table->setHeaderTitle("Backups for $dbName");
    $table->setHeaders([
      'id',
      'Date',
      'Type',
    ]);
    foreach ($backups as $backup) {
      $completedAt = new DateTime($backup->completedAt);
      $completedAt->setTimezone(new DateTimeZone('America/Los_Angeles'));
      $table->addRows([
        [
          $backup->id,
          $completedAt->format('Y-m-d H:m:s a T'),
          $backup->type,
        ],
      ]);
    }
    $table->render();
  }

  /**
   * Delete all ondemand backups for a given database.
   *
   * @param string $appName
   * @param string $env
   * @param string $dbName
   *
   * @command db:backup:delete-ondemand
   */
  public function deleteOndemandBackups($appName, $env, $dbName) {
    try {
      $appUuId = $this->getUuidFromName($appName);
    }
    catch (Exception $e) {
    }
    try {
      $envUuId = $this->getEnvUuIdFromApp($appUuId, $env);
    }
    catch (Exception $e) {
    }
    $backups = $this->databaseBackupAdapter->getAll($envUuId, $dbName);

    foreach ($backups as $backup) {
      if ($backup->type === 'ondemand') {
        $backupId = $backup->id;
        $this->say("Deleting Ondemand Backup ${backupId}");
        $this->deleteDbBackup($envUuId, $dbName, $backupId);
      }
    }
  }

}
