<?php

namespace OsuWams\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Exception;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;

class DomainCommand extends AcquiaCommand {

  public function __construct() {
    parent::__construct();
  }

  /**
   * Flush a set of domains from Acquia Cloud.
   *
   * @param array $options
   * @option $app The Acquia Cloud Application name: prod:shortname
   * @option $env The Environment short name: dev|prod|test
   * @option $domain The list of domain(s) to flush from Acquia Cache.
   *
   * @command flush:sites
   * @usage flush:sites
   * @usage flush:sites --app prod:app
   * @usage flush:sites --env dev
   * @usage flush:sites --app prod:app --env dev --domain test,test2,test3
   */
  public function flushSiteVarnish(
    array $options = [
      'app' => NULL,
      'env' => NULL,
      'domain' => NULL,
    ]
  ) {
    $appName = $this->getAppName($options);
    try {
      $appUuid = $this->getUuidFromName($appName);
    }
    catch (Exception $exception) {
      $this->say('Incorrect Application ID.');
    }
    $environment = $this->getEnvName($options, $appUuid);
    try {
      $envUuId = $this->getEnvUuIdFromApp($appUuid, $environment);
    }
    catch (Exception $exception) {
      $this->say('Incorrect Environment and Application id.');
    }
    if (is_null($options['domain'])) {
      $domainList = $this->getDomains($envUuId);
      $domainDeleteHelper = new ChoiceQuestion('Which Domain(s) do you want to delete, separate multiple by comma', $domainList);
      $domainDeleteHelper->setMultiselect(TRUE);
      /** @var array $domainsFlush */
      $domainsFlush = $this->doAsk($domainDeleteHelper);
    }
    else {
      // Create an array from a comma seperated string.
      $domainList = explode(",", $options['domain']);
      // Removing any whitespace.
      $domainsFlush = array_map(fn($domain) => trim($domain), $domainList);
    }
    $this->output()
      ->writeln('Flushing Domains ' . implode(',', $domainsFlush));
    try {
      $this->flushVarnish($envUuId, $domainsFlush);
    }
    catch (Exception $e) {
      $this->say($e->getMessage());
    }
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
   * Create a new Domain in the given environment.
   *
   * @param array $options
   * @option $app The Acquia Cloud Application name: prod:shortname
   * @option $env The Environment short name: dev|prod|test
   * @option $domain The domain to create
   *
   * @command domain:create
   * @usage domain:create --app prod:app --env dev --domain example.com
   */
  public function newDomain(array $options = [
    'app' => NULL,
    'env' => NULL,
    'domain' => NULL,
  ]
  ) {
    $appName = $this->getAppName($options);
    try {
      $appUuId = $this->getUuidFromName($appName);
    }
    catch (Exception $exception) {
      $this->say('Incorrect Application ID.');
    }
    $environment = $this->getEnvName($options, $appUuId);
    try {
      $envUuId = $this->getEnvUuIdFromApp($appUuId, $environment);
    }
    catch (Exception $exception) {
      $this->say('Incorrect Environment and Application id.');
    }
    if (is_null($options['domain'])) {
      $domainQuestion = new Question("What domain do you want to create in $appName application and $environment? Enter multiple domains separated by comma.");
      $domainName = $this->doAsk($domainQuestion);
      $domainName = explode(',', $domainName);
      $domainName = array_map(fn($domain) => trim($domain), $domainName);
    }
    else {
      // Create an array from a comma seperated string.
      $domainList = explode(",", $options['domain']);
      // Removing any whitespace.
      $domainName = array_map(fn($domain) => trim($domain), $domainList);
    }
    if (!empty($domainName)) {
      if (count($domainName) > 1) {
        $makeItSo = $this->confirm("Do you want to create these domains: " . implode(",", $domainName) . "?");
      }
      else {
        $makeItSo = $this->confirm("Do you want to create this domain: " . $domainName[0] . "?");
      }
      if ($makeItSo) {
        foreach ($domainName as $domain) {
          $this->createDomain($envUuId, $domain);
        }
      }
      else {
        $this->say("Aborting");
      }
    }
  }

  /**
   * @param array $options
   *
   * @option The Acquia Cloud Application name: prod:shortname
   * @option $domain The Production domain to use in creating Dev/Stage domains
   *
   * @command domain:create:devstage
   */
  public function createDevStageDomains(array $options = [
    'app' => NULL,
    'domain' => NULL,
  ]
  ) {
    $appName = $this->getAppName($options);
    try {
      $appUuId = $this->getUuidFromName($appName);
    }
    catch (Exception $exception) {
      $this->say('Incorrect Application ID.');
    }
    if (is_null($options['domain'])) {
      $domainName = $this->ask("What is the Production domain you want to use to create the Development and Staging domains?");
    }
    else {
      $domainName = $options['domain'];
    }
    [$domainShort] = explode('.', $domainName, 2);
    if ($appName === 'prod:d8cws') {
      $devDomain = $domainShort . '.dev.oregonstate.edu';
      $stageDomain = $domainShort . '.stage.oregonstate.edu';
    }
    elseif ($appName === 'prod:d7cws') {
      $devDomain = $domainShort . '.dev.acquia.cws.oregonstate.edu';
      $stageDomain = $domainShort . '.stage.acquia.cws.oregonstate.edu';
    }
    else {
      $devDomain = $this->ask("What is your Development domain?");
      $stageDomain = $this->ask("What is your Staging domain?");
    }

    $makeItSo = $this->confirm("Do you want to create the Development:$devDomain and Staging:$stageDomain for $domainName?", TRUE);
    if ($makeItSo) {
      try {
        $devEnvUuId = $this->getEnvUuIdFromApp($appUuId, 'dev');
      }
      catch (Exception $exception) {
        $this->say($exception->getMessage());
      }
      try {
        $stageEnvUuId = $this->getEnvUuIdFromApp($appUuId, 'test');
      }
      catch (Exception $exception) {
        $this->say($exception->getMessage());
      }
      $this->createDomain($devEnvUuId, $devDomain);
      $this->say("Created Development:$devDomain");
      $this->createDomain($stageEnvUuId, $stageDomain);
      $this->say("Created Staging:$stageDomain");
    }
    else {
      $this->say("Aborting");
    }
  }

  /**
   * Retrieve a list of domains.
   *
   * @param array $options
   * @option $app The Acquia Cloud Application name: prod:shortname
   * @option $env The Environment short name: dev|prod|test
   * @option $format Format the result data. Available formats:
   * csv,json,list,null,php,print-r,sections,string,table,tsv,var_dump,var_export,xml,yaml
   * @option $fields Available fields: hostname,cname,default,active,wildcard
   *
   * @command domain:list
   *
   * @usage domain:list
   * @usage domain:list --app=prod:app
   * @usage domain:list --env=prod
   * @usage domain:list --app=prod:app --env=prod --format=json
   */
  public
  function listDomains(array $options = [
    'app' => NULL,
    'env' => NULL,
    'format' => 'table',
    'fields' => 'hostname',
  ]
  ) {
    $appName = $this->getAppName($options);
    // Attempt to get the UUID of this application.
    try {
      $appUuId = $this->getUuidFromName($appName);
    }
    catch (Exception $e) {
      $this->say('Incorrect Application ID.');
    }
    $environment = $this->getEnvName($options, $appUuId);
    try {
      $envUuId = $this->getEnvUuIdFromApp($appUuId, $environment);
    }
    catch (Exception $e) {
      $this->say('Incorrect Environment and Application id.');
    }
    $domainList = $this->listAllDomains($envUuId);
    return new RowsOfFields($domainList);
  }

  /**
   * Delete domains from the given environment.
   *
   * @param array $options
   * @option $app The Acquia Cloud Application name: prod:shortname
   * @option $env The Environment short name: dev|prod|test
   * @option $domain The domain to delete
   *
   * @command domain:delete
   * @usage domain:delete
   * @usage domain:delete --app prod:app
   * @usage domain:delete --env test
   * @usage domain:delete --domain test.com
   * @usage domain:delete --app prod:app --env dev --domain test.com
   */
  public
  function domainDelete(array $options = [
    'app' => NULL,
    'env' => NULL,
    'domain' => NULL,
    'yes|y' => FALSE,
  ]
  ) {
    $appName = $this->getAppName($options);
    try {
      $appUuId = $this->getUuidFromName($appName);
    }
    catch (Exception $e) {
      $this->say('Incorrect Application ID.');
    }
    $environment = $this->getEnvName($options, $appUuId);
    try {
      $envUuId = $this->getEnvUuIdFromApp($appUuId, $environment);
    }
    catch (Exception $e) {
      $this->say('Incorrect Environment and Application id.');
    }
    if (is_null($options['domain'])) {
      $domainList = $this->getDomains($envUuId);
      $domainDeleteHelper = new ChoiceQuestion('Which Domain(s) do you want to delete, separate multiple by comma', $domainList);
      $domainDeleteHelper->setMultiselect(TRUE);
      /** @var array $domainDeleteList */
      $domainDeleteList = $this->doAsk($domainDeleteHelper);
    }
    else {
      // Create an array from a comma seperated string.
      $domainList = explode(",", $options['domain']);
      // Removing any whitespace.
      $domainDeleteList = array_map(fn($domain) => trim($domain), $domainList);
    }
    if (!is_null($domainDeleteList)) {
      $makeItSo = $options['yes'];
      if (!$makeItSo) {
        if (count($domainDeleteList) > 1) {
          $makeItSo = $this->confirm("Do you want to delete these domains: " . implode(",", $domainDeleteList) . "?");
        }
        else {
          $makeItSo = $this->confirm("Do you want to delete this domain: " . $domainDeleteList[0] . "?");
        }
      }
      if ($makeItSo) {
        foreach ($domainDeleteList as $domainToDelete) {
          $this->say("Deleting domain {$domainToDelete}");
          $this->deleteDomain($envUuId, $domainToDelete);
        }
      }
    }
  }

}
