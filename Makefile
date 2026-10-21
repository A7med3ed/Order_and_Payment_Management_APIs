APP_EXEC ?= docker compose exec -T app

.PHONY: up down build shell migrate fresh seed test pint ledger-verify docs

up:
	docker compose up -d --build --wait --wait-timeout 300

down:
	docker compose down

build:
	docker compose build

shell:
	docker compose exec app sh

migrate:
	$(APP_EXEC) php artisan migrate --force

fresh:
	$(APP_EXEC) php artisan migrate:fresh --seed

seed:
	$(APP_EXEC) php artisan db:seed

test:
	$(APP_EXEC) composer test

pint:
	$(APP_EXEC) php vendor/bin/pint --test

ledger-verify:
	$(APP_EXEC) php artisan ledger:verify

docs:
	$(APP_EXEC) php artisan l5-swagger:generate
	$(APP_EXEC) php scripts/export-openapi.php
