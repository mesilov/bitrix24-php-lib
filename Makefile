# This file is part of the bitrix24-app-core package.
#
#  © Maksim Mesilov <mesilov.maxim@gmail.com>
#
#  For the full copyright and license information, please view the MIT-LICENSE.txt
#  file that was distributed with this source code.

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