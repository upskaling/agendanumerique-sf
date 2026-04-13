.PHONY: help cs phpstan test coverage rector audit docker-up docker-down

help: ## Affiche l'aide
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'

cs: ## Corrige le code style
	php vendor/bin/php-cs-fixer fix src

cs-dry: ## Vérifie le code style sans modifier
	php vendor/bin/php-cs-fixer fix src --diff --dry-run

phpstan: ## Analyse statique
	php vendor/bin/phpstan analyze --memory-limit=1G

test: ## Lance les tests
	php vendor/bin/simple-phpunit

coverage: ## Génère le rapport de coverage
	XDEBUG_MODE=coverage php vendor/bin/simple-phpunit --coverage-html=var/coverage

rector: ## Refactoring automatique
	vendor/bin/rector process

audit: ## Audit de sécurité des dépendances
	composer audit

docker-up: ## Démarre l'environnement Docker
	docker compose up -d

docker-down: ## Arrête l'environnement Docker
	docker compose down

fixtures: ## Charge les fixtures
	symfony console doctrine:fixtures:load --no-interaction
