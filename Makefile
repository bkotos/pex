help:
	@cat help.txt

test.php: start
	@docker-compose exec php bash -c "php examples/test.php"

php: start
	@docker-compose exec php bash -c "php -a"

bash: start
	@docker-compose exec php bash

install:
	@docker-compose up composer

start: install
	@docker-compose up -d php

stop:
	@docker-compose stop php
