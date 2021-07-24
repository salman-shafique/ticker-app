# Ticker APP

This app is built with Laravel. Which is a web application framework with expressive, elegant syntax. Laravel attempts
to take the pain out of development by easing common tasks used in the majority of web projects.

## Services

This application is built with following services running on docker container. More detail can be found in docker-compose.yml
file.

- MySQL
- Redis
- Nginx
- PHP

## How to setup

Follow these steps to run the project:

```bash
$ # Download Docker and run Docker, Skip it if you already have docker installed 
$ curl -fsSL https://get.docker.com -o get-docker.sh
$ sh get-docker.sh
$ apt-get install docker-compose
$ # Clone the repo and cd to project folder
$ git clone <repo_url>
$ # create .env file and set DB passwords and other environment variables
$ cp .env.example .env
$ # Spin up the docker, add -d arg at end to run in background
$ docker-compose up --build 
$ # Access the application in browser: http://localhost or server IP
```

## Application Overview

The project has following sections:

> API

All api controllers are in "app/Http/Controllers/Api" Folder

> API Documentation with Swagger UI

For api documentation and visit
[http://localhost/api/documentation](http://localhost/api/documentation).

> Test cases

All test cases are in "tests/Unit" Folder

## Run Test Cases
To run the test cases execute this command.
```bash
$ docker exec -it ticker-php php artisan test
```


