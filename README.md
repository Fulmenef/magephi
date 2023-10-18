# Magephi
![status](https://img.shields.io/badge/status-stable-important.svg?cacheSeconds=2592000)
![php](https://img.shields.io/badge/php-^8.1-blue.svg?cacheSeconds=2592000)
![symfony](https://img.shields.io/badge/symfony-6.1-darkgreen.svg?cacheSeconds=2592000)
[![GitHub tag (latest by date)](https://img.shields.io/github/v/tag/fulmenef/magephi.svg)](https://github.com/fulmenef/magephi/tags)
[![GitHub Issues](https://img.shields.io/github/issues/fulmenef/magephi.svg)](https://github.com/fulmenef/magephi/issues)
![Contributions welcome](https://img.shields.io/badge/contributions-welcome-green.svg)
![license](https://img.shields.io/badge/license-MIT-purple.svg?cacheSeconds=2592000)

Magephi is a [Symfony](https://github.com/symfony/symfony) based PHP CLI designed to manage Magento 2 projects environment.

Its main functionality are the installation/start/stop of your environment but it can also initialize a Magento 2 project
 with its environment, give you access to services, verify your environment match the prerequisites, import a database etc.
 
For now Magephi has been only tested on Mac with the environment [docker-magento2](https://github.com/EmakinaFR/docker-magento2).

## Installation

```
composer global require fulmenef/magephi
```

## Usage

```bash
# List Magephi commands
magephi 

# Execute a specific command
magephi xxxxx

# Display the help message of a specific command
magephi xxxxx --help

# List all available commands (i.e. Symfony included)
magephi list
```

## Demo

<p>
  <img src="https://gist.githubusercontent.com/fulmenef/6d269a661b9ef62c015d0b961b34d762/raw/22420f21ec32705b6aecd78a0f02eb25a191b608/magephi.gif"
    width="900" alt="demo"/>
</p>

## Functionality

#### General
- `magephi prerequisites` to check if your system match the prerequisites to use the environment.
- `magephi import` to import a database and update the urls.
- `magephi update` to update Magephi.

#### Environment
- `magephi create` Initialize a new project with the environment. See [here](#project-initialization) for more details about the command.
- `magephi install` to install the environment (build + start).
- `magephi status` to give you a quick look on the project status (equivalent to docker-compose ps and mutagen list).
- `magephi start` to start the environment.
- `magephi stop` to stop the environment.
- `magephi cache` to flush Magento and Redis cache.
- `magephi uninstall` to fully uninstall the environment. This will remove volumes and container but not the files on your local system.
- `magephi backup|restore` Generate or restore a backup of your database and environment configuration.

#### Docker
- `magephi php|mysql|nginx|redis` to connect to containers.
- `magephi build` to build the containers. The docker/local/.env file must have been filled before.

#### Magento
- `magephi magento:install` to install Magento after you have installed the environment.

## Details

#### Project initialization
You can initialize a project with the `magephi create`. If you are in an empty directory, the project will be installed inside it,
if not you'll be asked for the project name and a directory with that name will be created and used as work directory.

At the same time as the Magento 2 dependency, some development dependencies are added.

Also, a custom .gitignore is set, you can have a look of the content [here](https://github.com/Fulmenef/magephi/blob/master/src/Command/Environment/CreateCommand.php#L118). 

##### Composer

- [Docker Magento 2](https://github.com/EmakinaFR/docker-magento2): Docker environment for your project
- [PhpStan](https://github.com/phpstan/phpstan): Static code analyser for PHP
- [PhpStan extension for Magento](https://github.com/bitExpert/phpstan-magento)
- [PhpCsFixer](https://github.com/FriendsOfPHP/PHP-CS-Fixer): A tool to automatically fix PHP Coding Standards issues 
- [Security Checker](https://github.com/sensiolabs/security-checker): Command line tool that checks if your application uses dependencies with known security vulnerabilities
- [Security Advisories](https://github.com/Roave/SecurityAdvisories): Composer tool to help to prevent installing packages with know vulnerabilities

##### Yarn

- [Husky](https://github.com/typicode/husky): Tool to trigger action on git hooks
- [Lint-Staged](https://github.com/okonet/lint-staged): Linter to apply checks on staged files, used with Husky.
- [Eslint](https://eslint.org/): Static code analyser for Javascript
- [Eslint extension for Magento](https://github.com/magento-research/magento-eslint)
- [Stylelint](https://stylelint.io/): Linter for LESS/CSS files

###### Example of configuration for Husky and Lint-Staged

The following will run PHPCsFixer and PHPStan on each PHP and PHTML files, Stylelint for each LESS files and Eslint for 
javascript files each time you run a `git commit`.
```json
// To be placed at the end of the package.json

"husky": {
    "hooks": {
        "pre-commit": "lint-staged --relative --shell"
    }
},
"lint-staged": {
    "!(app/etc/*).php|*.phtml": [
        "php ./vendor/bin/php-cs-fixer fix --config .php_cs",
        "php ./vendor/bin/phpstan analyze --level=max --no-progress"
    ],
    "app/code/YourVendor/**/*.less|app/design/frontend/YourVendor/**/*.less": [
        "php ./node_modules/.bin/stylelint --fix"
    ],
    "app/code/YourVendor/**/*.js|app/design/frontend/YourVendor/**/*.js": [
        "php ./node_modules/.bin/eslint --fix"
    ]
}
```

## Contributing
Pull requests are welcome. For major changes, please open an issue first to discuss what you would like to change.

If you would like to see your environment managed by Magephi, please open an issue.

## License
[MIT](https://choosealicense.com/licenses/mit/)
