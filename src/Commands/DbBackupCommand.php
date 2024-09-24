<?php

namespace OsuWams\Commands;

use AcquiaCloudApi\Endpoints\DatabaseBackups;
use Consolidation\OutputFormatters\FormatterManager;
use Consolidation\OutputFormatters\Options\FormatterOptions;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use DateTime;
use DateTimeZone;
use Exception;
use Symfony\Component\Console\Question\ChoiceQuestion;

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
   * @param array $options An array of options
   * @option $app The Acquia Cloud Application name: prod:shortname
   * @option $env The Environment short name: dev|prod|test
   * @option $dbname The Database Name to get backups for.
   * @option $backupId The ID of the backup to delete.
   * @option $format Format the result data. Available formats:
   *  csv,json,list,null,php,print-r,sections,string,table,tsv,var_dump,var_export,xml,yaml
   * @option $fields Available fields: Cron ID (id), Label, (label), Command
   *
   * @throws \Exception
   * @command db:backup:create
   */
  public function backupDbCreate(array $options = [
    'app' => NULL,
    'env' => NULL,
    'dbname' => NULL,
    'format' => 'table',
    'fields' => '',
  ]
  ) {
    if (is_null($options['app'])) {
      $this->say('Getting Applications...');
      $appHelper = new ChoiceQuestion('Select which Acquia Cloud Application you want to operate on', $this->getApplicationsId());
      $appName = $this->doAsk($appHelper);
    }
    else {
      $appName = $options['app'];
    }
    try {
      $appUuId = $this->getUuidFromName($appName);
    }
    catch (Exception $e) {
      $this->say('Incorrect Application ID.');
    }
    if (is_null($options['env'])) {
      // Get a list of environments for this App UUID.
      $this->writeln('Getting Environment ID\'s...');
      $envList = $this->getEnvironments($appUuId);
      // Get the Env for the scheduled jobs.
      $envHelper = new ChoiceQuestion('Which Environment do you want to backup a database for...', $envList);
      $environment = $this->doAsk($envHelper);
    }
    else {
      $environment = $options['env'];
    }
    try {
      $envUuId = $this->getEnvUuIdFromApp($appUuId, $environment);
    }
    catch (Exception $e) {
      $this->say('Incorrect Environment and Application id.');
    }
    if (is_null($options['dbname'])) {
      // Get database names.
      $this->writeln("Getting Database Name's...");
      $dbNames = $this->getDatabases($appUuId);
      $dbNameHelper = new ChoiceQuestion('Which Database do you want to backup?', $dbNames);
      $dbName = $this->doAsk($dbNameHelper);
    }
    else {
      $dbName = $options['dbname'];
    }
    $makeItSo = $this->confirm("Do you want to create a backup for {$dbName}?", "y");
    if ($makeItSo) {
      $this->createDbBackup($dbName, $envUuId);
      $this->say("Backup created");
    }
    else {
      $this->say("Aborting.");
    }
  }

  /**
   * Delete a Database backup.
   *
   * @param array $options An array of options
   * @option $app The Acquia Cloud Application name: prod:shortname
   * @option $env The Environment short name: dev|prod|test
   * @option $dbname The Database Name to get backups for.
   * @option $backupId The ID of the backup to delete.
   * @option $format Format the result data. Available formats:
   *  csv,json,list,null,php,print-r,sections,string,table,tsv,var_dump,var_export,xml,yaml
   * @option $fields Available fields: Cron ID (id), Label, (label), Command
   *
   * @command db:backup:delete
   * @throws \Exception
   */
  public function deleteBackupDb(array $options = [
    'app' => NULL,
    'env' => NULL,
    'dbname' => NULL,
    'backupId' => NULL,
    'format' => 'table',
    'fields' => '',
  ]
  ) {
    if (is_null($options['app'])) {
      $this->say('Getting Applications...');
      $appHelper = new ChoiceQuestion('Select which Acquia Cloud Application you want to operate on', $this->getApplicationsId());
      $appName = $this->doAsk($appHelper);
    }
    else {
      $appName = $options['app'];
    }
    try {
      $appUuId = $this->getUuidFromName($appName);
    }
    catch (Exception $e) {
      $this->say('Incorrect Application ID.');
    }
    if (is_null($options['env'])) {
      // Get a list of environments for this App UUID.
      $this->writeln('Getting Environment ID\'s...');
      $envList = $this->getEnvironments($appUuId);
      // Get the Env for the scheduled jobs.
      $envHelper = new ChoiceQuestion('Which Environment do you want to see the Database list for...', $envList);
      $environment = $this->doAsk($envHelper);
    }
    else {
      $environment = $options['env'];
    }
    try {
      $envUuId = $this->getEnvUuIdFromApp($appUuId, $environment);
    }
    catch (Exception $e) {
      $this->say('Incorrect Environment and Application id.');
    }
    if (is_null($options['dbname'])) {
      // Get database names.
      $this->writeln("Getting Database Name's...");
      $dbNames = $this->getDatabases($appUuId);
      $dbNameHelper = new ChoiceQuestion('Which Database do you want to list backups for?', $dbNames);
      $dbName = $this->doAsk($dbNameHelper);
    }
    else {
      $dbName = $options['dbname'];
    }
    if (is_null($options['backupId'])) {
      $this->writeln("Getting Backups...");
      $backups = $this->databaseBackupAdapter->getAll($envUuId, $dbName);
      $choices = [];
      $backupIdMap = [];
      /** @var \AcquiaCloudApi\Response\BackupResponse $backup */
      foreach ($backups as $backup) {
        $completedAt = new DateTime($backup->completedAt);
        $completedAt->setTimezone(new DateTimeZone('America/Los_Angeles'));
        $formattedCompletedAt = $completedAt->format('Y-m-d H:i:s');
        $humanString = "ID: $backup->id Completed at $formattedCompletedAt, Type $backup->type";
        $choices[] = $humanString;
        $backupIdMap[$humanString] = $backup->id;
      }
      $backupIdHelper = new ChoiceQuestion('Which backup to delete?', $choices);
      $selectedOption = $this->doAsk($backupIdHelper);
      $backupId = $backupIdMap[$selectedOption];
    }
    else {
      $backupId = $options['backupId'];
    }
    $makeItSo = $this->confirm("Do you want to delete this backup with the id of ${backupId}", 'y');
    if ($makeItSo) {
      $this->databaseBackupAdapter->delete($envUuId, $dbName, $backupId);
    }
    else {
      $this->say("Aborting");
    }
  }

  /**
   * List all backups for a given database.
   *
   * Optional arguments: app, env, dbname. If app and/or env are not provided,
   * a helper will ask you to select from a generated list.
   *
   * @param array $options An array of options
   * @option $app The Acquia Cloud Application name: prod:shortname
   * @option $env The Environment short name: dev|prod|test
   * @option $dbname The Database Name to get backups for.
   * @option $format Format the result data. Available formats:
   *  csv,json,list,null,php,print-r,sections,string,table,tsv,var_dump,var_export,xml,yaml
   * @option $fields Available fields: Cron ID (id), Label, (label), Command
   *
   * @throws \Exception
   * @command db:backup:list
   */
  public function listBackupDbs(array $options = [
    'app' => NULL,
    'env' => NULL,
    'dbname' => NULL,
    'format' => 'table',
    'fields' => '',
  ]
  ) {
    if (is_null($options['app'])) {
      $this->say('Getting Applications...');
      $appHelper = new ChoiceQuestion('Select which Acquia Cloud Application you want to operate on', $this->getApplicationsId());
      $appName = $this->doAsk($appHelper);
    }
    else {
      $appName = $options['app'];
    }
    // Attempt to get the UUID of this application.
    try {
      $appUuId = $this->getUuidFromName($appName);
    }
    catch (Exception $e) {
      $this->say('Incorrect Application ID.');
    }
    if (is_null($options['env'])) {
      // Get a list of environments for this App UUID.
      $this->writeln('Getting Environment ID\'s...');
      $envList = $this->getEnvironments($appUuId);
      // Get the Env for the scheduled jobs.
      $envHelper = new ChoiceQuestion('Which Environment do you want to see the Database list for...', $envList);
      $environment = $this->doAsk($envHelper);
    }
    else {
      $environment = $options['env'];
    }
    try {
      $envUuId = $this->getEnvUuIdFromApp($appUuId, $environment);
    }
    catch (Exception $e) {
      $this->say('Incorrect Environment and Application id.');
    }
    if (is_null($options['dbname'])) {
      // Get database names.
      $this->writeln("Getting Database Name's...");
      $dbNames = $this->getDatabases($appUuId);
      $dbNameHelper = new ChoiceQuestion('Which Database do you want to list backups for?', $dbNames);
      $dbName = $this->doAsk($dbNameHelper);
    }
    else {
      $dbName = $options['dbname'];
    }
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
   * @param array $options An array of options
   * @option $app The Acquia Cloud Application name: prod:shortname
   * @option $env The Environment short name: dev|prod|test
   * @option $dbname The Database Name to get backups for.
   * @option $format Format the result data. Available formats:
   *
   * @command db:backup:delete-ondemand
   */
  public function deleteOndemandBackups(array $options = [
    'app' => NULL,
    'env' => NULL,
    'dbname' => NULL,
    'format' => 'table',
    'fields' => '',
  ]
  ) {
    if (is_null($options['app'])) {
      $this->say('Getting Applications...');
      $appHelper = new ChoiceQuestion('Select which Acquia Cloud Application you want to operate on', $this->getApplicationsId());
      $appName = $this->doAsk($appHelper);
    }
    else {
      $appName = $options['app'];
    }
    // Attempt to get the UUID of this application.
    try {
      $appUuId = $this->getUuidFromName($appName);
    }
    catch (Exception $e) {
      $this->say('Incorrect Application ID.');
    }
    if (is_null($options['env'])) {
      // Get a list of environments for this App UUID.
      $this->writeln('Getting Environment ID\'s...');
      $envList = $this->getEnvironments($appUuId);
      // Get the Env for the scheduled jobs.
      $envHelper = new ChoiceQuestion('Which Environment do you want to see the Database list for...', $envList);
      $environment = $this->doAsk($envHelper);
    }
    else {
      $environment = $options['env'];
    }
    try {
      $envUuId = $this->getEnvUuIdFromApp($appUuId, $environment);
    }
    catch (Exception $e) {
      $this->say('Incorrect Environment and Application id.');
    }
    if (is_null($options['dbname'])) {
      // Get database names.
      $this->writeln("Getting Database Name's...");
      $dbNames = $this->getDatabases($appUuId);
      $dbNameHelper = new ChoiceQuestion('Which Database do you want to delete all On Demand backups for?', $dbNames);
      $dbName = $this->doAsk($dbNameHelper);
    }
    else {
      $dbName = $options['dbname'];
    }
    $backups = $this->databaseBackupAdapter->getAll($envUuId, $dbName);
    $makeItSo = $this->confirm("You are about to delete all ondemand backups for ${dbName} in ${appName}, are you sure?", 'y');
    if ($makeItSo) {
      foreach ($backups as $backup) {
        if ($backup->type === 'ondemand') {
          $backupId = $backup->id;
          $this->say("Deleting Ondemand Backup ${backupId}");
          $this->deleteDbBackup($envUuId, $dbName, $backupId);
        }
      }
    }
    else {
      $this->say("Aborting");
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
    $dailyBackups = array_filter($allBackups->getArrayCopy(), function($backup) {
      return $backup->type === "daily";
    });
    // Sort the backup date/time to ensure we have the latest at position 1.
    usort($dailyBackups, function($a, $b) {
      return $a->completedAt < $b->completedAt;
    });
    $backupId = $dailyBackups[0]->id;
    file_put_contents($dbName . '.sql.gz', $this->databaseBackupAdapter->download($envUuId, $dbName, $backupId));
  }

}
