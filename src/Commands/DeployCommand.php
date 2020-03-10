<?php


namespace OsuWams\Commands;


use Exception;
use Symfony\Component\Console\Question\ChoiceQuestion;

/**
 * Class DeployCommand
 *
 * @package OsuWams\Commands
 */
class DeployCommand extends AcquiaCommand {

  public function __construct() {
    parent::__construct();

  }

  /**
   * Deploy a site from on environment to the other.
   *
   * @command deploy:site
   */
  public function deploySite() {
    // Ask for which application to deploy in.
    $appHelper = new ChoiceQuestion('Select which Acquia Cloud Application you want to deploy a site on', $this->getApplicationsId());
    $appName = $this->doAsk($appHelper);
    // Attempt to get the UUID of this application.
    try {
      $appUuId = $this->getUuidFromName($appName);
    }
    catch (Exception $e) {
      $this->say('Incorrect Application ID.');
    }
    // Get a list of environments for this App UUID.
    $envList = $this->getEnvironments($appUuId);
    // Get the From Env for this deploy.
    $fromEnvHelper = new ChoiceQuestion('Select which Environment to deploy from', $envList);
    $fromEnv = $this->doAsk($fromEnvHelper);
    // Get the To Env for this deploy.
    $toEnvHelper = new ChoiceQuestion('Select which Environment to deploy to', array_diff($envList, [$fromEnv]));
    $toEnv = $this->doAsk($toEnvHelper);
    // Get th Site to deploy.
    $siteDbList = $this->getDatabases($appUuId);
    $siteHelper = new ChoiceQuestion('Select which Site to deploy.', $siteDbList);
    $siteDb = $this->doAsk($siteHelper);
    $siteUrl = str_replace('_', '.', $siteDb);
    try {
      $envUuidFrom = $this->getEnvUuIdFromApp($appUuId, $fromEnv);
    }
    catch (Exception $e) {
    }
    try {
      $envUuidTo = $this->getEnvUuIdFromApp($appUuId, $toEnv);
    }
    catch (Exception $e) {
    }
    $verifyAnswer = $this->ask("You are about to deploy ${siteUrl} from {$fromEnv} to ${toEnv} in the application ${appName}, do you wish to continue? (y/n)");
    if ($verifyAnswer === 'y') {
      $this->say("Creating Backup in Destination Environment");
      // DO the site deploy with DB backup in destination environment.
      $this->createDbBackup($siteDb, $envUuidTo);
      // Need to wait for task.
      $this->say("Coping Database from ${fromEnv} to ${toEnv}.");
      // Copy DB to destination environment.
      $this->copyDb($siteDb, $envUuidFrom, $envUuidTo);
      // Need to wait for task.
      // Copy files
      $this->say("Coping files from ${fromEnv} to ${toEnv}.");
      $this->rsyncFiles($appUuId, $siteUrl, $envUuidFrom, $envUuidTo);
      // Flush varnish.
      switch ($toEnv) {
        case 'prod':
          $prodUrls = [
            $siteUrl,
            substr_replace('.oregonstate.edu', '.prod.acquia.cws.oregonstate.edu', $siteUrl),
          ];
          $this->flushVarnish($envUuidTo, $prodUrls);
          break;
        case 'test':
          $siteUrl = substr_replace('.oregonstate.edu', '.stage.acquia.cws.oregonstate.edu', $siteUrl);
          $this->flushVarnish($envUuidTo, [$siteUrl]);
          break;
        case 'dev':
          $siteUrl = substr_replace('.oregonstate.edu', '.dev.acquia.cws.oregonstate.edu', $siteUrl);
          $this->flushVarnish($envUuidTo, [$siteUrl]);
          break;
        default:
          return;
      }
    }
    else {
      exit();
    }
  }

}
