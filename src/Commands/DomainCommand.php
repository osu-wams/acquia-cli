<?php


namespace OsuWams\Commands;


use Consolidation\OutputFormatters\FormatterManager;
use Consolidation\OutputFormatters\Options\FormatterOptions;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Exception;
use Symfony\Component\Console\Question\ChoiceQuestion;

class DomainCommand extends AcquiaCommand {

  public function __construct() {
    parent::__construct();
  }

  /**
   * Flush a set of domains from Acquia Cloud,
   *
   * @throws \Exception
   */
  public function flushSiteVarnish() {
    $appHelper = new ChoiceQuestion('Select which Acquia Cloud Application you want to deploy a site on', $this->getApplicationsId());
    $appName = $this->doAsk($appHelper);
    $appUuid = $this->getUuidFromName($appName);
    $envList = $this->getEnvironments($appUuid);
    $envHelper = new ChoiceQuestion('Select which Environment to perform the flush on.', $envList);
    $env = $this->doAsk($envHelper);
    $envUuid = $this->getEnvUuIdFromApp($appUuid, $env);
    $domains = $this->getDomains($envUuid);
    $domainHelper = new ChoiceQuestion("Which Domains do you want to flush? Separate multiple by comma", $domains);
    $domainHelper->setMultiselect(TRUE);
    /** @var array $domain */
    $domain = $this->doAsk($domainHelper);
    $this->output()
      ->writeln('Flushing Domains ' . implode(',', $domain));
    $this->flushVarnish($envUuid, $domain);
  }

  /**
   * Flush a single domain.
   *
   * @param string $appname
   * @param string $envName
   * @param string $domainName
   *
   * @command flush:single:site
   */
  public function flushSingleSiteVarnish(string $appName, string $envName, string $domainName) {
    $appUuid = $this->getUuidFromName($appName);
    $envUuid = $this->getEnvUuIdFromApp($appUuid, $envName);
    $this->flushVarnish($envUuid, [$domainName]);
  }

  /**
   * Create a new Domain in the given environment.
   *
   * @param string $appName
   *  The Acquia Cloud Application Name.
   * @param string $envName
   *  The environment you wish to create the domain in.
   * @param string $domainName
   *  The domain name to create
   *
   * @command domain:create
   * @usage domain:create prod:AppName dev example.com
   * @throws \Exception
   */
  public function newDomain(string $appName, string $envName, string $domainName) {
    $appUuId = $this->getUuidFromName($appName);
    $envUuId = $this->getEnvUuIdFromApp($appUuId, $envName);
    $this->createDomain($envUuId, $domainName);
  }

  /**
   * Retrieve a list of domains.
   *
   * @command domain:list
   * @throws \Exception
   */
  public function listDomains($options = [
    'format' => 'table',
    'fields' => '',
  ]) {
    $this->say('Getting Applications...');
    $appHelper = new ChoiceQuestion('Select which Acquia Cloud Application you want to operate on', $this->getApplicationsId());
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
    $envHelper = new ChoiceQuestion('Which Environment do you want to see the domain list for...', $envList);
    $environment = $this->doAsk($envHelper);
    try {
      $envUuId = $this->getEnvUuIdFromApp($appUuId, $environment);
    }
    catch (Exception $e) {
      $this->say('Incorrect Environment and Application id.');
    }
    $domainList = $this->getDomains($envUuId);
    $rows = [];
    foreach ($domainList as $domain) {
      $rows[] = ['domain_name' => $domain];
    }
    $opts = new FormatterOptions([], $options);
    $opts->setInput($this->input);
    $opts->setFieldLabels(['domain_name' => 'Domain Name']);
    $opts->setDefaultStringField('domain_name');
    $formatterManager = new FormatterManager();
    $formatterManager->write($this->output, $opts->getFormat(), new RowsOfFields($rows), $opts);

    //    $domains = new UnstructuredListData($domainList);
    //    $this->writeln($domains);
  }

  /**
   * Delete domains from the given environment.
   *
   * @command domain:delete
   */
  public function domainDelete() {
    $this->say('Getting Applications...');
    $appHelper = new ChoiceQuestion('Select which Acquia Cloud Application you want to operate on', $this->getApplicationsId());
    $appName = $this->doAsk($appHelper);
    try {
      $appUuId = $this->getUuidFromName($appName);
    }
    catch (Exception $e) {
      $this->say('Incorrect Application ID.');
    }
    $this->say('Getting Environment ID\'s...');
    $envList = $this->getEnvironments($appUuId);
    $envHelper = new ChoiceQuestion('Which Environment do you want to delete domains from...', $envList);
    $environment = $this->doAsk($envHelper);
    try {
      $envUuId = $this->getEnvUuIdFromApp($appUuId, $environment);
    }
    catch (Exception $e) {
      $this->say('Incorrect Environment and Application id.');
    }
    $domainList = $this->getDomains($envUuId);
    $domainDeleteHelper = new ChoiceQuestion('Which Domain(s) do you want to delete, separate multiple by comma', $domainList);
    $domainDeleteHelper->setMultiselect(TRUE);
    /** @var array $domainDeleteList */
    $domainDeleteList = $this->doAsk($domainDeleteHelper);
    if (!is_null($domainDeleteList)) {
      if (count($domainDeleteList) > 1) {
        $makeItSo = $this->confirm("Do you want to delete these domains: " . implode(",", $domainDeleteList) . "?");
      }
      else {
        $makeItSo = $this->confirm("Do you want to delete this domain: " . $domainDeleteList[0] . "?");
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
