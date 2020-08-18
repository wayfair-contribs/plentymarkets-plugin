# Docker file for (re)building the UI into the host's "ui" folder
#
# usage:
#    $ docker build . -t build-plenty-ui -f ./build_ui.dockerfile
#    $ docker run -v "$(pwd)"/angular:/angular:ro \
#        -v "$(pwd)"/ui:/ui \ 
#        -w /angular \
#        build-plenty-ui npm run build

MAINTAINER Wayfair Emerging Markets team <ERPSupport@Wayfair.com>
FROM ubuntu:latest

# TODO: install node, etc - see https://www.digitalocean.com/community/tutorials/how-to-install-node-js-on-ubuntu-20-04

# WARNING: putting the reinstall here requires rebuilding the image if we make changes to modules.
RUN npm run reinstall
