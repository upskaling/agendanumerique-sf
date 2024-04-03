phpcs:
	docker pull oskarstark/php-cs-fixer-ga:latest
	docker run --rm -it -w=/app -v $(CURDIR):/app oskarstark/php-cs-fixer-ga:latest --diff

phpstan:
	symfony php vendor/bin/phpstan analyze --memory-limit=1G

phpunit:
	symfony php vendor/bin/phpunit