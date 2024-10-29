PHP = php
CONSOLE = $(PHP) bin/console
UNIT = $(PHP) vendor/bin/phpunit

test: ## Run the tests
	$(UNIT) --testdox
