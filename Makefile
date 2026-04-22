setup:
	./bin/setup

test:
	php artisan test

lint:
	@echo "No dedicated lint task yet"

dev:
	php artisan serve

build:
	npm run build
