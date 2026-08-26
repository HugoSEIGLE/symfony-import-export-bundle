COMPOSER = composer
QLTY = qlty

.DEFAULT_GOAL := help

.PHONY: help install validate test coverage lint phpstan qlty-check qlty-fmt qlty-smells qlty-metrics quality

help: ## Display the available commands
	@awk 'BEGIN {FS = ":.*## "; printf "Usage: make <target>\n\n"} /^[a-zA-Z0-9_-]+:.*## / {printf "  %-14s %s\n", $$1, $$2}' $(MAKEFILE_LIST)

install: ## Install Composer dependencies
	$(COMPOSER) install

validate: ## Validate Composer metadata and PSR-4 autoloading
	$(COMPOSER) validate --strict
	$(COMPOSER) dump-autoload --optimize --strict-psr

test: ## Run the test suite
	$(COMPOSER) test

coverage: ## Run the test suite and generate coverage.xml
	$(COMPOSER) test:coverage

lint: ## Check coding standards without modifying files
	$(COMPOSER) lint

phpstan: ## Run PHPStan
	$(COMPOSER) phpstan

qlty-check: ## Run Qlty checks on changed files
	$(QLTY) check

qlty-fmt: ## Format changed files with Qlty
	$(QLTY) fmt

qlty-smells: ## Find code smells in the whole project
	$(QLTY) smells --all

qlty-metrics: ## Display project complexity metrics
	$(QLTY) metrics --all --max-depth=2 --sort complexity --limit 10

quality: validate test lint phpstan qlty-check ## Run all project quality checks
