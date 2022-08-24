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
   * Deploy a site from one environment to an other.
   *
   * @command deploy:site
   */
  public function deploySite() {
    // Ask for which application to deploy in.
    $this->writeln('Getting Applications...');
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
    $this->writeln('Getting Environment ID\'s...');
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
    if (count($siteDbList) > 1) {
      $this->yell("This will not work on a multi site, use deploy:multisite instead", 80, "red");
    }
    $verifyAnswer = $this->ask("You are about to deploy ${siteDb} from {$fromEnv} to ${toEnv} in the application ${appName}, do you wish to continue? (y/n)");
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
      $this->copyFiles($envUuidFrom, $envUuidTo);
    }
    else {
      exit();
    }
  }

  /**
   * Deploy a site from one environment to the other in a multisite.
   *
   * @command deploy:multisite
   */
  public function deployMultiSite() {
    // Ask for which application to deploy in.
    $this->writeln('Getting Applications...');
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
    $this->writeln('Getting Environment ID\'s...');
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
    $verifySiteUrl = $this->ask("Is this the correct domain ${siteUrl}");
    if ($verifySiteUrl === "n") {
      $updateSiteUrl = $this->ask("What is the correct domain of the site?");
      $siteUrl = $updateSiteUrl;
    }
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
      $checkToFlushVarnish = $this->ask("Flush varnish?");
      if ($checkToFlushVarnish === 'y') {
        $this->say('Getting Domains...');
        $domains = $this->getDomains($envUuidTo);
        $domainHelper = new ChoiceQuestion("Which Domains do you want to flush? Separate multiple by comma", $domains);
        $domainHelper->setMultiselect(TRUE);
        /** @var array $domain */
        $domain = $this->doAsk($domainHelper);
        $this->output()
          ->writeln('Flushing Domains ' . implode(',', $domain));
        $this->flushVarnish($envUuidTo, $domain);
      }
    }
    else {
      exit();
    }
  }

}
