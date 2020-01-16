# Magephi
![status](https://img.shields.io/badge/status-beta-important.svg?cacheSeconds=2592000)
![php](https://img.shields.io/badge/php-^7.4-blue.svg?cacheSeconds=2592000)
![symfony](https://img.shields.io/badge/symfony-5.0.2-darkgreen.svg?cacheSeconds=2592000)
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
curl https://github.com/Fulmenef/magephi/raw/gh-pages/magephi.phar
mv magephi.phar /usr/local/bin/magephi
```

You can update the application directly with the command `magephi update`

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

<p align="center">
  <img src="https://gist.githubusercontent.com/fulmenef/6d269a661b9ef62c015d0b961b34d762/raw/22420f21ec32705b6aecd78a0f02eb25a191b608/magephi.gif"
    width="900" alt="demo"/>
</p>


## Contributing
Pull requests are welcome. For major changes, please open an issue first to discuss what you would like to change.

If you would like to see your environment managed by Magephi, please open an issue.

## License
[MIT](https://choosealicense.com/licenses/mit/)