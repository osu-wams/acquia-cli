<?php


namespace OsuWams\Commands;


use AcquiaCloudApi\Endpoints\DatabaseBackups;
use Consolidation\OutputFormatters\FormatterManager;
use Consolidation\OutputFormatters\Options\FormatterOptions;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use DateTime;
use DateTimeZone;
use Exception;

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
  public function listBackupDbs($appName, $env, $dbName, $options = [
    'format' => 'table',
    'fields' => '',
  ]) {
    $appUuId = $this->getUuidFromName($appName);
    $envUuId = $this->getEnvUuIdFromApp($appUuId, $env);
    $backups = $this->databaseBackupAdapter->getAll($envUuId, $dbName);
    $rows = [];
    /** @var \AcquiaCloudApi\Response\BackupResponse $backup */
    foreach ($backups as $backup) {
      $completedAt = new DateTime($backup->completedAt);
      $completedAt->setTimezone(new DateTimeZone('America/Los_Angeles'));
      $rows[] = [
        'id' => $backup->id,
        'completedat' => $completedAt->format('Y-m-d H:m:s a T'),
        'type' => $backup->type,
      ];
    }
    $opts = new FormatterOptions([], $options);
    $opts->setInput($this->input);
    $opts->setFieldLabels([
      'id' => 'Backup ID',
      'completedat' => 'Completed At',
      'type' => 'Backup Type',
    ]);
    $opts->setDefaultStringField('id');

    $formatterManager = new FormatterManager();
    $formatterManager->write($this->output, $opts->getFormat(), new RowsOfFields($rows), $opts);
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

  /**
   * @command db:backup:download-latest
   */
  public function downloadLatestBackups($appName, $env, $dbName) {
    $appUuId = $this->getUuidFromName($appName);
    $envUuId = $this->getEnvUuIdFromApp($appUuId, $env);
    $allBackups = $this->databaseBackupAdapter->getAll($envUuId, $dbName);
    // Filter our backups to only include daily backups.
    $dailyBackups = array_filter($allBackups->getArrayCopy(), function ($backup) {
      return $backup->type === "daily";
    });
    // Sort the backup date/time to ensure we have the latest at position 1.
    usort($dailyBackups, function ($a, $b) {
      return $a->completedAt < $b->completedAt;
    });
    $backupId = $dailyBackups[0]->id;
    file_put_contents($dbName . '.sql.gz', $this->databaseBackupAdapter->download($envUuId, $dbName, $backupId));
  }

}
