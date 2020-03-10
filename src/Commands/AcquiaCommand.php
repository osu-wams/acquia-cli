<?php


namespace OsuWams\Commands;

use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Connector\Connector;
use AcquiaCloudApi\Endpoints\Applications;
use AcquiaCloudApi\Endpoints\Databases;
use AcquiaCloudApi\Endpoints\Domains;
use AcquiaCloudApi\Endpoints\Environments;
use AcquiaCloudApi\Endpoints\Notifications;
use AcquiaCloudApi\Response\OperationResponse;
use DateTime;
use DateTimeZone;
use Exception;
use OsuWams\OsuDatabaseBackups;
use Robo\Robo;
use Robo\Tasks;
use Symfony\Component\Console\Helper\ProgressBar;

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

  CONST SLEEP = 5;

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
   * Get a list of Application Names
   *
   * @return array
   *  Returns an array of all applications the user has access too.
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
    $db = new OsuDatabaseBackups($this->client);
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
          break(2);
        case self::TASKSTARTED:
        case self::TASKINPROGRESS:
          break;
        case self::TASKCOMPLETED:
          break(2);
        default:
          throw new Exception('Unknown notification status.');
          break(2);
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
    $progressBar = new ProgressBar($output);
    return $progressBar;
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
  protected function copyDb($dbname, $fromEnv, $toEnv) {
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
  protected function createDbBackup($dbname, $envUuid) {
    $db = new OsuDatabaseBackups($this->client);
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
  protected function rsyncFiles($appUuId, $siteName, $envUuIdFrom, $envUuIdTo) {
    $environments = new Environments($this->client);
    $fromUrl = $environments->get($envUuIdFrom)->sshUrl;
    $toUrl = $environments->get($envUuIdTo)->sshUrl;
    $fromUser = explode('@', $fromUrl);
    $toUser = explode('@', $toUrl);
    $this->taskRsync()
      ->fromHost($fromUrl)
      ->fromPath("/mnt/gfs/${fromUser[0]}/sites/${siteName}/")
      ->toPath("/tmp/${siteName}/")
      ->archive()
      ->compress()
      ->excludeVcs()
      ->run();
    $this->taskRsync()
      ->fromPath("/tmp/${siteName}/")
      ->toHost($toUrl)
      ->toPath("/mnt/gfs/${toUser[0]}/sites/${siteName}/")
      ->archive()
      ->compress()
      ->excludeVcs()
      ->run();
    $this->say('Deleting temporary file copy.');
    $this->_deleteDir("/tmp/${siteName}");
  }

  /**
   * Flush varnish cache for a given list of domains.
   *
   * @param string $envUuid
   * @param array $domainList
   *
   * @throws \Exception
   */
  protected function flushVarnish($envUuid, array $domainList) {
    $domains = new Domains($this->client);
    $response = $domains->purge($envUuid, $domainList);
    $this->waitForTask($response);
  }

}
