.PHONY:build

include .env

ifeq ($(ENV), development)
    include Makefile.development.mk
else ifneq ($(ENV), development)
	include Makefile.production.mk
endif

# Container management
######################
up:
	mkdir -p tmp/db
	docker-compose up -d

down:
	docker-compose down

reup: down up

build: down
	docker-compose build

# Container interaction
#######################
exec_app_cmd:
	docker-compose exec app bash

exec_php_cmd:
	docker-compose exec php bash -c "${CMD}"

# Commands
##########
app_sync_all: app_sync_trakt app_sync_tmdb

app_sync_trakt:
	make exec_php_cmd CMD="php bin/console.php app:sync-trakt --overwrite"

app_sync_tmdb:
	make exec_php_cmd CMD="php bin/console.php app:sync-tmdb"

app_sync_letterboxd:
	make exec_php_cmd CMD="php bin/console.php app:sync-letterboxd $(CSV_PATH)"

# Database
##########
db_migration_migrate:
	make exec_php_cmd CMD="vendor/bin/phinx migrate -c ./settings/phinx.php"

db_migration_rollback:
	make exec_php_cmd CMD="vendor/bin/phinx rollback -c ./settings/phinx.php"
