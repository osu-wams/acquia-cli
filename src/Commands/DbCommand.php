<?php

namespace OsuWams\Commands;

use AcquiaCloudApi\Endpoints\Databases;
use Consolidation\OutputFormatters\FormatterManager;
use Consolidation\OutputFormatters\Options\FormatterOptions;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Exception;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;

class DbCommand extends AcquiaCommand {

  /**
   * @var \AcquiaCloudApi\Endpoints\Databases
   */
  protected $dbAdapter;

  public function __construct() {
    parent::__construct();
    $this->dbAdapter = new Databases($this->client);
  }

  /**
   * List All Databases for the given Application.
   *
   * @param array $options
   *  An array of options
   * @option $app The Acquia Cloud Application name: prod:shortname
   * @option $format Format the result data. Available formats:
   * csv,json,list,null,php,print-r,sections,string,table,tsv,var_dump,var_export,xml,yaml
   * @option $fields Available fields: Cron ID (id), Label, (label), Command
   *
   * @command db:list
   *
   * @usage db:list
   * @usage db:list --app=prod:app
   * @usage db:list --app=prod:app --format=json
   */
  public function listDatabases(array $options = [
    'app' => NULL,
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
      $this->say('Incorrect Application Name.');
    }
    $envDbs = $this->dbAdapter->getAll($appUuId);

    $rows = [];
    /** @var \AcquiaCloudApi\Response\DatabaseResponse $db */
    foreach ($envDbs as $db) {
      $rows[] = ['name' => $db->name];
    }
    $opts = new FormatterOptions([], $options);
    $opts->setInput($this->input);
    $opts->setFieldLabels(['name' => 'Database Name']);
    $opts->setDefaultStringField('name');
    $formatterManager = new FormatterManager();
    $formatterManager->write($this->output, $opts->getFormat(), new RowsOfFields($rows), $opts);
  }

  /**
   * Deploy Database to a given environment.
   *
   * @param $appName
   * @param $dbName
   * @param $fromEnv
   * @param $toEnv
   *
   * @command db:deployto
   *
   * @throws \Exception
   */
  public function deployDbTo($appName, $dbName, $fromEnv, $toEnv) {
    $appUuId = $this->getUuidFromName($appName);
    $envUuIdFrom = $this->getEnvUuIdFromApp($appUuId, $fromEnv);
    $envUuIdTo = $this->getEnvUuIdFromApp($appUuId, $toEnv);
    $this->copyDb($dbName, $envUuIdFrom, $envUuIdTo);
  }

  /**
   * Create a new Database.
   *
   * @command db:create
   * @usage db:create
   * @usage db:create --app prod:app
   * @usage db:create --domain test.com
   * @usage db:create --app prod:app --domain test.com,test2.com
   * @throws \Exception
   */
  public function createDb(array $options = [
    'app' => NULL,
    'domain' => NULL,
  ]
  ) {
    $appName = $this->getAppName($options);
    try {
      $appUuId = $this->getUuidFromName($appName);
    }
    catch (Exception $e) {
      $this->say('Incorrect Application ID.');
    }
    if (is_null($options['domain'])) {
      $dbNameHelper = new Question('What is the production domain name (Enter multiple domains separated by a comma): ');
      $dbName = $this->doAsk($dbNameHelper);
    }
    else {
      $dbName = $options['domain'];
    }
    if (!is_null($dbName)) {
      $dbNameArr = explode(',', $dbName);
      $dbCreateList = array_map(fn($db) => str_replace('.', '_', strtolower(trim($db))), $dbNameArr);
      if (count($dbCreateList) > 1) {
        $makeItSo = $this->confirm("Do you want to create these databases: " . implode(', ', $dbCreateList) . "?");
      }
      else {
        $makeItSo = $this->confirm("Do you want to create this database: " . $dbCreateList[0] . "?");
      }
      if ($makeItSo) {
        foreach ($dbCreateList as $db) {
          $this->say('Creating database > ' . $db);
          $this->createDatabase($appUuId, $db);
        }
      }
    }
    else {
      $this->say('Nothing provided, stopping');
      exit();
    }
  }

  /**
   * Delete a database.
   *
   * @command db:delete
   * @usage db:delete --app prod:app
   * @usage db:delete --dbname test.com
   * @usage db:delete --app prod:app --dbname test.com,test2.com
   */
  public function deleteDb(array $options = [
    'app' => NULL,
    'dbname' => NULL,
  ]
  ) {
    $appName = $this->getAppName($options);
    try {
      $appUuId = $this->getUuidFromName($appName);
    }
    catch (Exception $e) {
      $this->say('Incorrect Application ID.');
    }
    if (is_null($options['dbname'])) {
      $this->say('Getting Databases...');
      $dbHelper = new ChoiceQuestion('Select which Database to delete', $this->getDatabases($appUuId));
      $dbHelper->setMultiselect(TRUE);
      $dbName = $this->doAsk($dbHelper);
    }
    else {
      $dbName = explode(',', $options['dbname']);
    }
    if (!is_null($dbName)) {
      $dbCreateList = array_map(fn($db) => str_replace('.', '_', strtolower(trim($db))), $dbName);
      if (count($dbCreateList) > 1) {
        $makeItSo = $this->confirm("Do you want to delete these databases: " . implode(', ', $dbCreateList) . "?");
      }
      else {
        $makeItSo = $this->confirm("Do you want to delete this database: " . $dbCreateList[0] . "?");
      }
      if ($makeItSo) {
        foreach ($dbCreateList as $db) {
          $this->deleteDatabase($appUuId, $db);
        }
      }
    }
  }

}
