##
## ----------------------------------------------------------------------------
##   MAGEPHI
## ----------------------------------------------------------------------------
##

box: ## Compiles the project into a PHAR archive
	composer dump-env prod
	./bin/console cache:clear
	./bin/console cache:warmup
	box compile
	rm .env.local.php
.PHONY: box

install: ## Executes a copy/paste analysis
	rm -rf ${HOME}/.magephi/cache/* ${HOME}/.magephi/logs/*
	mv -f ./build/magephi.phar /usr/local/bin/magephi
.PHONY: install

php-cs-fixer: ## Fixes code style in all PHP files
	./vendor/bin/php-cs-fixer fix --verbose
.PHONY: php-cs-fixer

phpstan: ## Executes a static analysis at the higher level on all PHP files
	./vendor/bin/phpstan analyze src --level=max --memory-limit=1G --verbose
.PHONY: phpstan

security: ## Executes a security audit on all PHP dependencies
	bin/console security:check
.PHONY: security

help:
	@grep -E '(^[a-zA-Z_-]+:.*?##.*$$)|(^##)' $(MAKEFILE_LIST) \
		| awk 'BEGIN {FS = ":.*?## "}; {printf "\033[32m%-30s\033[0m %s\n", $$1, $$2}' \
		| sed -e 's/\[32m##/[33m/'
.DEFAULT_GOAL := help
