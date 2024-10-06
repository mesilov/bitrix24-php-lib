# This file is part of the bitrix24-app-core package.
#
#  © Maksim Mesilov <mesilov.maxim@gmail.com>
#
#  For the full copyright and license information, please view the MIT-LICENSE.txt
#  file that was distributed with this source code.
#!/usr/bin/env make

export COMPOSE_HTTP_TIMEOUT=120
export DOCKER_CLIENT_TIMEOUT=120

default:
	@echo "make needs target:"
	@egrep -e '^\S+' ./Makefile | grep -v default | sed -r 's/://' | sed -r 's/^/ - /'

# linters
lint-phpstan:
	vendor/bin/phpstan --memory-limit=1G analyse
lint-rector:
	vendor/bin/rector process --dry-run
lint-rector-fix:
	vendor/bin/rector process

# unit tests
test-unit:
	vendor/bin/phpunit --testsuite unit_tests --display-warnings

test-integration:
	vendor/bin/phpunit --testsuite integration_tests --display-warnings


# docker file

docker-up:
	docker compose up --build -d

docker-down:
	docker compose down --remove-orphans

#======================================
restart: down up

init:
	@echo "remove all containers"
	docker-compose down --remove-orphans
	@echo "build containers"
	docker-compose build
	@echo "install dependencies"
	docker-compose run --rm php-cli composer install
#	@echo "run database migrations…"
#	docker-compose run --rm php-cli php bin/doctrine doctrine:migrations:migrate --no-interaction
	@echo "change owner of var folder for access from container"
    docker-compose run --rm php-cli chown -R www-data:www-data /var/www/html/var/
	@echo "run application…"
	docker-compose up -d


up:
	@echo "run application…"
	docker-compose up -d

down:
	@echo "stop application and remove containers"
	docker-compose down --remove-orphans

down-clear:
	@echo "stop application and remove containers with volumes"
	docker-compose down -v --remove-orphans


composer-install:
	@echo "install dependencies…"
	docker-compose run --rm php-cli composer install

composer-update:
	@echo "update dependencies…"
	docker-compose run --rm php-cli composer update

# вызов composer с любыми параметрами
# Примеры:
# make composer install
# make composer "install --no-dev"
composer:
	docker-compose run --rm php-cli composer $(filter-out $@,$(MAKECMDGOALS))

%:
	@: # silence

dev-dump-cache:
	composer dumpautoload

#======================================
cli-bash:
	docker-compose run --rm php-cli sh $(filter-out $@,$(MAKECMDGOALS))

# static code analysis
test-run-phpstan:
	docker-compose run --rm php-cli php vendor/bin/phpstan analyse --memory-limit 2G

# unit-tests
test-run-unit-tests:
	docker-compose run --rm php-cli php vendor/bin/phpunit --testsuite=unit --testdox

# functional-tests, work with test database
test-run-functional-tests:
	docker-compose run --rm php-cli php bin/doctrine orm:schema-tool:drop --force
	docker-compose run --rm php-cli php bin/doctrine orm:schema-tool:create
	docker-compose run --rm php-cli php vendor/bin/phpunit --testsuite=functional --testdox