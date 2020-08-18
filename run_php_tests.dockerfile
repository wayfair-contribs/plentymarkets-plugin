# Docker file for running the PHP tests / PHP code
# usage:
#    $ docker build . -t plenty-php-runner -f ./build_ui.dockerfile
#    $ docker run plenty-php-runner ./run_tests test

MAINTAINER Wayfair Emerging Markets team <ERPSupport@Wayfair.com>
FROM ubuntu:latest

# copy only what we need to build the PHP and run the tests
COPY ./src /src
COPY ./tests /tests
COPY ./run_tests /run_tests

RUN apt-get update

# Install PHP 7.0 for compliance with Plentymarkets - no PHP 7.1 or up!
RUN apt-get install python-software-properties
RUN add-apt-repository ppa:ondrej/php
RUN apt-get update
RUN apt-get install -y php7.0

# TODO: install python that Composer needs?

# TODO: Install Composer that is compatible with PHP 7.0

# Build the PHP project
RUN composer install && composer dump
