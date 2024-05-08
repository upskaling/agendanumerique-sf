phpcs:
	symfony php vendor/bin/php-cs-fixer fix src --diff

phpstan:
	symfony php vendor/bin/phpstan analyze --memory-limit=1G

phpunit:
	symfony php vendor/bin/phpunit

audit:
	composer audit