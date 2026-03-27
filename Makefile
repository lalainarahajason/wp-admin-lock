.PHONY: up down logs shell cli install test phpcs

up:
	docker compose -f docker/docker-compose.yml --env-file docker/.env up -d

down:
	docker compose -f docker/docker-compose.yml --env-file docker/.env down

logs:
	docker compose -f docker/docker-compose.yml --env-file docker/.env logs -f wordpress

shell:
	docker compose -f docker/docker-compose.yml --env-file docker/.env exec wordpress bash

cli:
	docker compose -f docker/docker-compose.yml --env-file docker/.env run --rm wpcli wp $(cmd)

install:
	docker compose -f docker/docker-compose.yml --env-file docker/.env run --rm wpcli wp core install \
		--url=http://localhost:8000 \
		--title="Lebo Secu Dev" \
		--admin_user=admin \
		--admin_password=admin \
		--admin_email=admin@localhost.dev \
		--skip-email
	docker compose -f docker/docker-compose.yml --env-file docker/.env run --rm wpcli wp plugin activate lebo-secu

test:
	./vendor/bin/phpunit --configuration phpunit.xml

phpcs:
	./vendor/bin/phpcs --standard=phpcs.xml plugin/
