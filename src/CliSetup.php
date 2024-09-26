<?php

namespace OsuWams;

use OsuWams\Exception\FileSaveException;
use Robo\Tasks;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * This class handles the CLI setup for authenticating with Acquia Cloud.
 */
class CliSetup extends Tasks {

  /**
   * Exit status code indicating a configuration error.
   *
   * The value of this constant is 78.
   *
   * @var int
   */
  private const EX_CONFIG = 78;

  /**
   *  Exit status code for normal operations.
   *
   * The value of this constant is 0;
   *
   * @var int
   */
  private const EX_NORMAL = 0;

  /**
   * The input interface.
   *
   * @var \Symfony\Component\Console\Input\InputInterface
   */
  protected $input;

  /**
   * The Output interface.
   *
   * @var \Symfony\Component\Console\Output\OutputInterface
   */
  protected $output;

  /**
   * Constructor method for initializing input and output interfaces.
   *
   * @param InputInterface $input The input interface instance.
   * @param OutputInterface $output The output interface instance.
   *
   * @return void
   */
  public function __construct(InputInterface $input, OutputInterface $output) {
    $this->input = $input;
    $this->output = $output;
  }

  /**
   * Helper method for setting up CLI authentication with Acquia Cloud.
   *
   * Prompts the user to confirm whether they want to set up authentication.
   * If confirmed, it asks for the Acquia Cloud API Key and Secret, then
   * attempts to save the credentials. Provides feedback on the success or
   * failure of saving the credentials.
   *
   * @return int
   */
  public function cliSetupHelper(): int {
    $startConfirm = $this->confirm("Not yet configured to authenticate with Acquia Cloud, do you want to setup?", "y");
    if ($startConfirm) {
      $apiKey = $this->askHidden("Please enter an Acquia Cloud API Key");
      $apiSecret = $this->askHidden("Please enter an Acquia Cloud Secret");
      try {
        if ($this->saveCredentials($apiKey, $apiSecret)) {
          $this->say("Credentials saved successfully.");
          return self::EX_NORMAL;
        }
        else {
          $this->say("Failed to save credentials.");
          return self::EX_CONFIG;
        }
      }
      catch (FileSaveException $e) {
        $this->writeln($e->getMessage());
        return $e->getCode() ?: 1;
      }
    }
    else {
      $this->say("Setup cancelled. Exiting...");
      return self::EX_NORMAL;
    }
  }

  /**
   * Saves API credentials to a configuration file.
   *
   * @param string $apiKey The API key to be saved.
   * @param string $apiSecret The API secret to be saved.
   *
   * @return bool Returns TRUE on success, FALSE on failure.
   * @throws \OsuWams\Exception\FileSaveException
   */
  private function saveCredentials(string $apiKey, string $apiSecret): bool {
    $configDir = $this->getConfigDir();
    if (!is_dir($configDir) && !mkdir($configDir, 0755, TRUE)) {
      return FALSE;
    }

    $configPath = "$configDir/acquia-cli.yml";
    $configData = [
      'acquia' => [
        'key' => $apiKey,
        'secret' => $apiSecret,
      ],
    ];
    $configContent = Yaml::dump($configData, 4, 2);

    if (file_put_contents($configPath, $configContent) === FALSE) {
      throw new FileSaveException("Failed to save the file: $configPath");
    }
    return TRUE;
  }

  /**
   * Retrieves the configuration directory path.
   *
   * @return string The path to the configuration directory.
   */
  private function getConfigDir(): string {
    return join(DIRECTORY_SEPARATOR, [
      getenv('HOME') ?: getenv('USERPROFILE'),
      '.acquia',
    ]);
  }

}
