# This file is part of the bitrix24-app-core package.
#
#  © Maksim Mesilov <mesilov.maxim@gmail.com>
#
#  For the full copyright and license information, please view the MIT-LICENSE.txt
#  file that was distributed with this source code.
#!/usr/bin/env make

export COMPOSE_HTTP_TIMEOUT=120
export DOCKER_CLIENT_TIMEOUT=120

# load default and personal env-variables
ENV := $(PWD)/.env
ENV_LOCAL := $(PWD)/.env.local
include $(ENV)
-include $(ENV_LOCAL)



coding-standards: vendor
	vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php --diff --verbose

default:
	@echo "make needs target:"
	@egrep -e '^\S+' ./Makefile | grep -v default | sed -r 's/://' | sed -r 's/^/ - /'

%:
	@: # silence

# Rule to print all environment variables for debugging
debug-print-env:
	@echo "DATABASE_HOST=$(DATABASE_HOST)"
	@echo "DATABASE_NAME=$(DATABASE_NAME)"
	@echo "DATABASE_USER=$(DATABASE_USER)"
	@echo "DATABASE_PASSWORD=$(DATABASE_PASSWORD)"

init:
	@echo "remove all containers"
	docker-compose down --remove-orphans
	@echo "build containers"
	docker-compose build
	@echo "install dependencies"
	docker-compose run --rm php-cli composer install
	@echo "change owner of var folder for access from container"
    docker-compose run --rm php-cli chown -R www-data:www-data /var/www/html/var/
	@echo "run application…"
	docker-compose up -d

up:
	@echo "run application…"
	docker-compose up --build -d

down:
	@echo "stop application and remove containers"
	docker-compose down --remove-orphans

down-clear:
	@echo "stop application and remove containers with volumes"
	docker-compose down -v --remove-orphans

restart: down up

# работа с контейнерами
php-cli-bash:
	docker-compose run --rm php-cli sh $(filter-out $@,$(MAKECMDGOALS))

# работа с composer
composer-install:
	@echo "install dependencies…"
	docker-compose run --rm php-cli composer install

composer-update:
	@echo "update dependencies…"
	docker-compose run --rm php-cli composer update

composer-dumpautoload:
	docker-compose run --rm php-cli composer dumpautoload
# вызов composer с любыми параметрами
# Примеры:
# make composer install
# make composer "install --no-dev"
composer:
	docker-compose run --rm php-cli composer $(filter-out $@,$(MAKECMDGOALS))

# check allowed licenses
lint-allowed-licenses:
	vendor/bin/composer-license-checker
# linters
lint-phpstan:
	docker-compose run --rm php-cli php vendor/bin/phpstan analyse --memory-limit 2G
lint-rector:
	docker-compose run --rm php-cli php vendor/bin/rector process --dry-run
lint-rector-fix:
	docker-compose run --rm php-cli php vendor/bin/rector process
lint-cs-fixer:
	docker-compose run --rm php-cli php vendor/bin/php-cs-fixer fix --dry-run --diff --verbose
lint-cs-fixer-fix:
	docker-compose run --rm php-cli php vendor/bin/php-cs-fixer fix --diff --verbose

# unit-tests
test-run-unit:
	docker-compose run --rm php-cli php vendor/bin/phpunit --testsuite=unit_tests --display-warnings --testdox

# functional-tests, work with test database
test-run-functional: debug-print-env
	docker-compose run --rm php-cli php bin/doctrine orm:schema-tool:drop --force
	docker-compose run --rm php-cli php bin/doctrine orm:schema-tool:create
	docker-compose run --rm php-cli php bin/doctrine orm:schema-tool:update --dump-sql
	docker-compose run --rm php-cli php vendor/bin/phpunit --testsuite=functional_tests --display-warnings --testdox

# Запустить один функциональный тест с дебагером
run-one-functional-test: debug-print-env
	docker-compose run --rm php-cli php -dxdebug.start_with_request=yes vendor/bin/phpunit --filter 'testCreateExistingAccount' tests/Functional/Bitrix24Accounts/UseCase/InstallStart/HandlerTest.php

schema-drop:
	docker-compose run --rm php-cli php bin/doctrine orm:schema-tool:drop --force

schema-create:
	docker-compose run --rm php-cli php bin/doctrine orm:schema-tool:create

