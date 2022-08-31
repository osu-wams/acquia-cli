# Acquia Cloud CLI
This project integrates with Acquia cloud v2 API. There is another, more tested CLI tool out there 
by [Typhonius](https://github.com/typhonius/acquia_cli). I would recommend using that package first.
We wrote this one with our needs in mind. There are lots of similarities to the other Acquia CLI package 
in code and language.

## Get Started
You will need to create your V2 Acquia Cloud API and Secret. Documentation about setup can be found on [Acquia Cloud API](https://docs.acquia.com/acquia-cloud/develop/api/auth/)

* Copy acquia-cli.dist.yml to acquia-cli.yml and update the keys and secret.
* Run `composer install -o --no-dev` to get all dependencies.
## Running
Execute the cli via `./bin/acquiacli` and it will print a list of commands to the screen.

There is a deployment wizard that will prompt you for what application to deploy and walk you through all the steps to 
deploy a site from one environment to the other. There are commands to run the different parts separately.

The Process is:
* Take a database backup in the destination environment.
* Deploy the source database to the destination environment.
* Rsync the files from the source of the site to the destination of the site.
* Flush Acquia CLoud Varnish for the Domain.

## Commands

`backup:nightly application:name environment /full/path/to/backups`
