# Magephi
![status](https://img.shields.io/badge/status-alpha-important.svg?cacheSeconds=2592000)
![php](https://img.shields.io/badge/php-^7.1-blue.svg?cacheSeconds=2592000)
![symfony](https://img.shields.io/badge/symfony-4.3.8-darkgreen.svg?cacheSeconds=2592000)
[![GitHub tag (latest by date)](https://img.shields.io/github/v/tag/fulmenef/magephi.svg)](https://github.com/fulmenef/magephi/tags)
[![GitHub Issues](https://img.shields.io/github/issues/fulmenef/magephi.svg)](https://github.com/fulmenef/magephi/issues)
![Contributions welcome](https://img.shields.io/badge/contributions-welcome-green.svg)
![license](https://img.shields.io/badge/license-MIT-purple.svg?cacheSeconds=2592000)

Magephi is a [Symfony](https://github.com/symfony/symfony) based PHP CLI designed to manage Magento 2 projects using 
[docker-magento2](https://github.com/EmakinaFR/docker-magento2).
The main functionality is the automated installation, but the application also has other features such as connecting to
Docker containers, checking system requirements and more.

## Installation

So far, the only way to install it is to manually download the phar file.

## Usage

```bash
# List commands used to manage environments
magephi 

# Execute a specific command
magephi xxxxx

# Display the help message of a specific command
magephi xxxxx --help

# List all available commands (i.e. Symfony included)
magephi list
```

## Contributing
Pull requests are welcome. For major changes, please open an issue first to discuss what you would like to change.

## License
[MIT](https://choosealicense.com/licenses/mit/)