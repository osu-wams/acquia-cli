<?php

namespace OsuWams\Commands;

use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Connector\Connector;
use AcquiaCloudApi\Endpoints\Applications;
use AcquiaCloudApi\Endpoints\Crons;
use AcquiaCloudApi\Endpoints\DatabaseBackups;
use AcquiaCloudApi\Endpoints\Databases;
use AcquiaCloudApi\Endpoints\Domains;
use AcquiaCloudApi\Endpoints\Environments;
use AcquiaCloudApi\Endpoints\Notifications;
use AcquiaCloudApi\Response\OperationResponse;
use DateTime;
use DateTimeZone;
use Exception;
use Robo\Robo;
use Robo\Tasks;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Question\ChoiceQuestion;

/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 */
abstract class AcquiaCommand extends Tasks {

  const TASKFAILED = 'failed';

  const TASKCOMPLETED = 'completed';

  const TASKSTARTED = 'started';

  const TASKINPROGRESS = 'in-progress';

  const TIMEOUT = 300;

  const SLEEP = 5;

  /**
   * @var \AcquiaCloudApi\Connector\Client
   */
  protected $client;

  /**
   * AcquiaCli constructor.
   *
   * @throws \League\OAuth2\Client\Provider\Exception\IdentityProviderException
   */
  public function __construct() {
    $config = Robo::config()->get('acquia');
    $connector = new Connector($config);
    $this->client = Client::factory($connector);
  }

  /**
   * Get the Environment UUID for the provided AppUUID and Environment short
   * code.
   *
   * @param string $appUuId
   * @param string $env
   *
   * @return string
   *  The Environment UUID for the Provided Name.
   * @throws \Exception
   */
  protected function getEnvUuIdFromApp($appUuId, $env) {
    $environments = new Environments($this->client);
    foreach ($environments->getAll($appUuId) as $environment) {
      if ($environment->name === strtolower($env)) {
        return $environment->uuid;
      }
    }
    throw new Exception("Invalid App UUID and/or Environment");
  }

  /**
   * Get the Applications UUID for the provide name.
   *
   * @param string $appName
   *  The Acquia Cloud Application name.
   *
   * @return string
   *  Return the UUID of the provided application.
   * @throws \Exception
   */
  protected function getUuidFromName($appName) {
    $applications = new Applications($this->client);
    foreach ($applications->getAll() as $application) {
      if ($appName === $application->hosting->id) {
        return $application->uuid;
      }
    }
    throw new Exception('Unable to find that application.');
  }

  /**
   * Get all applications the user has access to.
   *
   * @return \AcquiaCloudApi\Response\ApplicationsResponse
   */
  protected function getAllApplications() {
    $applications = new Applications($this->client);
    return $applications->getAll();
  }

  /**
   * @param string $appUuId
   *   The Acquia Cloud APP UUID.
   *
   * @return \AcquiaCloudApi\Response\EnvironmentResponse
   *   The environment response.
   */
  protected function getEnvironment($appUuId) {
    $environments = new Environments($this->client);
    return $environments->get($appUuId);
  }

  /**
   * Get a list of databases for this App.
   *
   * @param string $appUuId
   *  The Acquia Cloud App UUID.
   *
   * @return array
   *  An array of Databases associated to this Acquia Cloud Application.
   */
  protected function getDatabases($appUuId) {
    $dbList = [];
    $dbs = new Databases($this->client);
    foreach ($dbs->getAll($appUuId) as $db) {
      $dbList[] = $db->name;
    }
    return $dbList;
  }

  /**
   * @param $envUuid
   * @param $dbName
   * @param $backupUuid
   *
   * @throws \Exception
   */
  protected function deleteDbBackup($envUuid, $dbName, $backupUuid) {
    $db = new DatabaseBackups($this->client);
    $response = $db->delete($envUuid, $dbName, $backupUuid);
    $this->waitForTask($response);
  }

  /**
   * Wait for a notification that the job is complete.
   *
   * @param OperationResponse $response
   *
   * @throws \Exception
   */
  protected function waitForTask($response) {
    $notificationArray = explode('/', $response->links->notification->href);
    if (empty($notificationArray)) {
      throw new Exception('Notification UUID not found.');
    }
    $notificationUUID = end($notificationArray);
    $start = new DateTime(date('c'));
    $timeZone = new DateTimeZone('America/Los_Angeles');
    $start->setTimezone($timeZone);
    $progress = $this->getProgressBar();
    $progress->setMessage('Looking for notification.');
    $progress->start();
    $notificationAdapter = new Notifications($this->client);

    while (TRUE) {
      $progress->advance(self::SLEEP);
      sleep(self::SLEEP);
      $notification = $notificationAdapter->get($notificationUUID);
      $progress->setMessage(sprintf('Notification %s', $notification->status));
      switch ($notification->status) {
        case self::TASKFAILED:
          throw new Exception('Acquia task failed.');
        case self::TASKSTARTED:
        case self::TASKINPROGRESS:
          break;
        case self::TASKCOMPLETED:
          break(2);
        default:
          throw new Exception('Unknown notification status.');
      }
      $current = new DateTime(date('c'));
      $current->setTimezone($timeZone);
      if (self::TIMEOUT <= ($current->getTimestamp() - $start->getTimestamp())) {
        throw new Exception('Acquia Task has timed out, go see why.');
      }
    }
    $progress->finish();
    $this->writeln(PHP_EOL);
    return TRUE;
  }

  /**
   * Generates a Progress Bar Object.
   *
   * @return \Symfony\Component\Console\Helper\ProgressBar
   */
  protected function getProgressBar() {
    $output = $this->output();
    return new ProgressBar($output);
  }

  /**
   * Copy a database from one environment to another.
   *
   * @param $dbname
   * @param $fromEnv
   * @param $toEnv
   *
   * @throws \Exception
   */
  protected function copyDb(string $dbname, string $fromEnv, string $toEnv) {
    $dbAdapter = new Databases($this->client);
    $response = $dbAdapter->copy($fromEnv, $dbname, $toEnv);
    $this->waitForTask($response);
  }

  /**
   * Create a database backup of.
   *
   * @param string $dbname
   *  The Database Name.
   * @param string $envUuid
   *  The Environment UUID.
   *
   * @throws \Exception
   */
  protected function createDbBackup(string $dbname, string $envUuid) {
    $db = new DatabaseBackups($this->client);
    $response = $db->create($envUuid, $dbname);
    $this->waitForTask($response);
  }

  /**
   * Run the Rsync task from to.
   *
   * @param string $appUuId
   * @param string $siteName
   * @param string $envUuIdFrom
   * @param string $envUuIdTo
   */
  protected function rsyncFiles(string $appUuId, string $siteName, string $envUuIdFrom, string $envUuIdTo) {
    $environments = new Environments($this->client);
    $fromUrl = $environments->get($envUuIdFrom)->sshUrl;
    $toUrl = $environments->get($envUuIdTo)->sshUrl;
    $fromUser = explode('@', $fromUrl);
    $toUser = explode('@', $toUrl);
    $this->taskRsync()
      ->fromHost($fromUrl)
      ->fromPath("/mnt/gfs/${fromUser[0]}/sites/$siteName/")
      ->toPath("/tmp/$siteName/")
      ->archive()
      ->compress()
      ->excludeVcs()
      ->run();
    $this->taskRsync()
      ->fromPath("/tmp/$siteName/")
      ->toHost($toUrl)
      ->toPath("/mnt/gfs/${toUser[0]}/sites/$siteName/")
      ->archive()
      ->compress()
      ->excludeVcs()
      ->run();
    $this->say('Deleting temporary file copy.');
    $this->_deleteDir("/tmp/$siteName");
  }

  /**
   * Flush varnish cache for a given list of domains.
   *
   * @param string $envUuid
   * @param array $domainList
   *
   * @throws \Exception
   */
  protected function flushVarnish(string $envUuid, array $domainList) {
    $domains = new Domains($this->client);
    $response = $domains->purge($envUuid, $domainList);
    $this->waitForTask($response);
  }

  /**
   * Get a list of domains for a given environment id.
   *
   * @param string $envUuid
   *  The Environment ID to query.
   *
   * @return array
   *  An array of domains.
   */
  protected function getDomains(string $envUuid) {
    $domainList = [];
    $domains = new Domains($this->client);
    /** @var \AcquiaCloudApi\Response\DomainResponse $domain */
    foreach ($domains->getAll($envUuid) as $domain) {
      $domainList[] = $domain->hostname;
    }
    return $domainList;
  }

  /**
   * List all domains for a given environment.
   *
   * @param string $envUuid
   *  The Acquia Cloud Environment UUID.
   *
   * @return array
   *  An array of domains with their respective details.
   */
  protected function listAllDomains(string $envUuid) {
    $domainList = [];
    $domains = new Domains($this->client);
    /** @var \AcquiaCloudApi\Response\DomainResponse $domain */
    foreach ($domains->getAll($envUuid) as $domain) {
      $domainList[$domain->hostname] = [
        'hostname' => $domain->hostname,
        'cname' => $domain->cnames,
        'default' => $domain->flags->default ? "True" : "False",
        'active' => $domain->flags->active ? "True" : "False",
        'wildcard' => $domain->flags->wildcard ? "True" : "False",
      ];
    }
    return $domainList;
  }

  protected function getCrons(string $envUuid) {
    $cronList = [];
    $crons = new Crons($this->client);
    /** @var \AcquiaCloudApi\Response\CronResponse $cron */
    foreach ($crons->getAll($envUuid) as $cron) {
      $cronList[$cron->id] = [
        "id" => $cron->id,
        "label" => $cron->label,
        "command" => $cron->command,
        "minute" => $cron->minute,
        "hour" => $cron->hour,
        "dayWeek" => $cron->dayWeek,
        "month" => $cron->month,
        "dayMonth" => $cron->dayMonth,
      ];
    }
    return $cronList;
  }

  /**
   * Create a cron job.
   *
   * @param string $envUuid
   *  The environment UUID.
   * @param string $command
   *  The cron command to execute.
   * @param string $frequency
   *  The frequency of the cron job.
   * @param string $label
   *  The label for the cron job.
   *
   * @return \AcquiaCloudApi\Response\OperationResponse
   */
  protected function createCron(string $envUuid, string $command, string $frequency, string $label): OperationResponse {
    $cron = new Crons($this->client);
    return $cron->create($envUuid, $command, $frequency, $label);
  }

  /**
   * Create a new Database.
   *
   * @param string $appUuId
   *  The Acquia Cloud Application UUID.
   * @param string $dbName
   *  The Database name to create.
   *
   * @return OperationResponse
   */
  protected function createDatabase(string $appUuId, string $dbName) {
    $database = new Databases($this->client);
    return $database->create($appUuId, $dbName);
  }

  /**
   * Delete a database.
   *
   * @param string $appUuId
   *  The Acquia Cloud Application UUID.
   * @param string $dbName
   *  The Database name to delete.
   *
   * @return \AcquiaCloudApi\Response\OperationResponse
   */
  protected function deleteDatabase(string $appUuId, string $dbName) {
    $database = new Databases($this->client);
    return $database->delete($appUuId, $dbName);
  }

  /**
   * Create a new Domain in the given Environment.
   *
   * @param string $envUuId
   *  The Acquia Cloud Environment UUID.
   * @param string $domainName
   *  The domain name to create.
   *
   * @return OperationResponse
   */
  protected function createDomain(string $envUuId, string $domainName) {
    $domain = new Domains($this->client);
    return $domain->create($envUuId, $domainName);
  }

  /**
   * Delete a Domain in the given Environment.
   *
   * @param string $envUuId
   *  The Acquia Cloud Environment UUID.
   * @param string $domainName
   *  The Domain to delete.
   *
   * @return OperationResponse
   */
  protected function deleteDomain(string $envUuId, string $domainName) {
    $domain = new Domains($this->client);
    return $domain->delete($envUuId, $domainName);
  }

  /**
   * Perform an application file copy command.
   *
   * @param string $envUuIdFrom
   *  The Acquia Cloud UUID Source.
   * @param string $envUuIdTo
   *  The Acquia Cloud UUID Target.
   *
   * @return OperationResponse
   */
  protected function copyFiles(string $envUuIdFrom, string $envUuIdTo) {
    $fileService = new Environments($this->client);
    return $fileService->copyFiles($envUuIdFrom, $envUuIdTo);
  }

  /**
   * Retrieves the application name from the provided options or prompts the
   * user to select one.
   *
   * @param array $options An associative array of options including the key
   *   'app' for the application name.
   *
   * @return string The name of the application.
   */
  protected function getAppName(array $options): string {
    if (is_null($options['app'])) {
      $this->say('Getting Applications...');
      $appHelper = new ChoiceQuestion('Select which Acquia Cloud Application you want to operate on', $this->getApplicationsId());
      return $this->doAsk($appHelper);
    }
    return $options['app'];
  }

  /**
   * Get a list of Application Names
   *
   * @return array
   *  Returns an array of all applications the user has access to.
   */
  protected function getApplicationsId() {
    $application = new Applications($this->client);
    $apps = $application->getAll();
    $appList = [];
    foreach ($apps as $app) {
      $appList[] = $app->hosting->id;
    }
    return $appList;
  }

  /**
   * Retrieves the environment name from the provided options or prompts the
   * user to select one based on the given application UUID.
   *
   * @param array $options An associative array of options including the key
   *   'env' for the environment name.
   * @param string $appUuId The UUID of the application for which to retrieve
   *   the environments.
   *
   * @return string The name of the environment.
   */
  protected function getEnvName(array $options, string $appUuId) {
    if (is_null($options['env'])) {
      // Get a list of environments for this App UUID.
      $this->writeln('Getting Environment IDs...');
      $envList = $this->getEnvironments($appUuId);
      // Get the Env for the scheduled jobs.
      $envHelper = new ChoiceQuestion('Which Environment do you want...', $envList);
      return $this->doAsk($envHelper);
    }
    return $options['env'];
  }

  /**
   * Get a list of environments for this App.
   *
   * @param string $appUuId
   *  The Acquia Cloud App UUID.
   *
   * @return array
   *  Return an array of environments for this application.
   */
  protected function getEnvironments($appUuId) {
    $envList = [];
    $environments = new Environments($this->client);
    foreach ($environments->getAll($appUuId) as $environment) {
      $envList[] = $environment->name;
    }
    return $envList;
  }

  /**
   * Deletes a Cron job associated with the given environment UUID and Cron ID.
   *
   * @param string $envUuid
   *  The UUID of the environment where the Cron job is located.
   * @param string $cronId
   *  The ID of the Cron job to be deleted.
   *
   * @return mixed
   *  Returns the response from the delete operation.
   */
  protected function deleteCron(string $envUuid, string $cronId) {
    $cron = new Crons($this->client);
    return $cron->delete($envUuid, $cronId);
  }

}
