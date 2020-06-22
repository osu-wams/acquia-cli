<?php


namespace OsuWams\Commands;


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

}
