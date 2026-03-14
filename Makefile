# This file is part of the bitrix24-app-core package.
#
#  © Maksim Mesilov <mesilov.maxim@gmail.com>
#
#  For the full copyright and license information, please view the MIT-LICENSE.txt
#  file that was distributed with this source code.
#!/usr/bin/env make

export COMPOSE_HTTP_TIMEOUT=120
export DOCKER_CLIENT_TIMEOUT=120

.DEFAULT_GOAL := help

%:
	@: # silence

# load default and personal env-variables
ENV := $(PWD)/.env
ENV_LOCAL := $(PWD)/.env.local
include $(ENV)
-include $(ENV_LOCAL)

.PHONY: help
help:
	@echo "-------------------------------"
	@echo "    bitrix24-php-lib Makefile"
	@echo "-------------------------------"
	@echo ""
	@echo "docker-init             - first installation"
	@echo "docker-up               - run docker"
	@echo "docker-down             - stop docker"
	@echo "docker-down-clear       - stop docker and remove volumes"
	@echo "docker-pull             - pull Docker images"
	@echo "docker-restart          - restart containers"
	@echo ""
	@echo "composer-install        - install dependencies"
	@echo "composer-update         - update dependencies"
	@echo "composer-dumpautoload   - regenerate autoload"
	@echo "composer-clear-cache    - clear composer cache"
	@echo "composer                - run composer and pass arguments"
	@echo ""
	@echo "lint-all                - run all linters"
	@echo "lint-allowed-licenses   - validate dependency licenses"
	@echo "lint-cs-fixer           - run php-cs-fixer in dry-run"
	@echo "lint-cs-fixer-fix       - run php-cs-fixer fix"
	@echo "lint-phpstan            - run phpstan"
	@echo "lint-rector             - run rector dry-run"
	@echo "lint-rector-fix         - run rector fix"
	@echo ""
	@echo "test-unit               - run unit tests"
	@echo "test-functional         - run functional tests"
	@echo "test-functional-one     - run one functional test with debugger"
	@echo ""
	@echo "doctrine-schema-drop    - drop database schema"
	@echo "doctrine-schema-create  - create database schema"
	@echo "php-cli-bash            - open shell in php-cli container"
	@echo "debug-show-env          - print db env variables"

.PHONY: docker-init
docker-init:
	@echo "remove all containers"
	docker compose down --remove-orphans
	@echo "pull Docker images"
	docker compose pull
	@echo "build containers"
	docker compose build
	@echo "install dependencies"
	docker compose run --rm php-cli composer install
	@echo "run application..."
	docker compose up -d

.PHONY: docker-up
docker-up:
	@echo "run application..."
	docker compose up --build -d

.PHONY: docker-down
docker-down:
	@echo "stop application and remove containers"
	docker compose down --remove-orphans

.PHONY: docker-down-clear
docker-down-clear:
	@echo "stop application and remove containers with volumes"
	docker compose down -v --remove-orphans

.PHONY: docker-pull
docker-pull:
	@echo "pull Docker images..."
	docker compose pull

.PHONY: docker-restart
docker-restart: docker-down docker-up

.PHONY: composer-install
composer-install:
	@echo "install dependencies..."
	docker compose run --rm php-cli composer install

.PHONY: composer-update
composer-update:
	@echo "update dependencies..."
	docker compose run --rm php-cli composer update

.PHONY: composer-dumpautoload
composer-dumpautoload:
	docker compose run --rm php-cli composer dumpautoload

.PHONY: composer-clear-cache
composer-clear-cache:
	docker compose run --rm php-cli composer clear-cache

.PHONY: composer
# call composer with any parameters
# make composer install
# make composer "install --no-dev"
composer:
	docker compose run --rm php-cli composer $(filter-out $@,$(MAKECMDGOALS))

.PHONY: lint-allowed-licenses
lint-allowed-licenses:
	docker compose run --rm php-cli vendor/bin/composer-license-checker

.PHONY: lint-cs-fixer
lint-cs-fixer:
	docker compose run --rm php-cli php vendor/bin/php-cs-fixer fix --dry-run --diff --verbose

.PHONY: lint-cs-fixer-fix
lint-cs-fixer-fix:
	docker compose run --rm php-cli php vendor/bin/php-cs-fixer fix --diff --verbose

.PHONY: lint-phpstan
lint-phpstan:
	docker compose run --rm php-cli php vendor/bin/phpstan analyse --memory-limit 2G

.PHONY: lint-rector
lint-rector:
	docker compose run --rm php-cli php vendor/bin/rector process --dry-run

.PHONY: lint-rector-fix
lint-rector-fix:
	docker compose run --rm php-cli php vendor/bin/rector process

.PHONY: lint-all
lint-all: lint-allowed-licenses lint-cs-fixer lint-phpstan lint-rector

.PHONY: test-unit
test-unit:
	docker compose run --rm php-cli php vendor/bin/phpunit --testsuite=unit_tests --display-warnings --testdox

.PHONY: debug-show-env
debug-show-env:
	@echo "DATABASE_HOST=$(DATABASE_HOST)"
	@echo "DATABASE_NAME=$(DATABASE_NAME)"
	@echo "DATABASE_USER=$(DATABASE_USER)"
	@echo "DATABASE_PASSWORD=$(DATABASE_PASSWORD)"

.PHONY: test-functional
test-functional: debug-show-env
	docker compose run --rm php-cli php bin/doctrine orm:schema-tool:drop --force
	docker compose run --rm php-cli php bin/doctrine orm:schema-tool:create
	docker compose run --rm php-cli php bin/doctrine orm:schema-tool:update --dump-sql
	docker compose run --rm php-cli php vendor/bin/phpunit --testsuite=functional_tests --display-warnings --testdox

.PHONY: test-functional-one
test-functional-one: debug-show-env
	docker compose run --rm php-cli php -dxdebug.start_with_request=yes vendor/bin/phpunit --filter 'testChangeDomainUrlWithHappyPath' tests/Functional/Bitrix24Accounts/UseCase/ChangeDomainUrl/HandlerTest.php

.PHONY: doctrine-schema-drop
doctrine-schema-drop:
	docker compose run --rm php-cli php bin/doctrine orm:schema-tool:drop --force

.PHONY: doctrine-schema-create
doctrine-schema-create:
	docker compose run --rm php-cli php bin/doctrine orm:schema-tool:create

.PHONY: php-cli-bash
php-cli-bash:
	docker compose run --rm php-cli sh $(filter-out $@,$(MAKECMDGOALS))
