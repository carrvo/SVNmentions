#!/bin/bash

sudo apt install apache2 php php-curl php-dom
sudo apt install subversion libapache2-mod-svn #php5-svn

#sudo apt install php-dev libsvn-dev
#sudo pecl install --nodeps svn-beta # svn has a beta version for PHP8.0.0 but PHP8.3.* is out
#echo "you can remove intermediate install using:"
#echo "sudo apt remove php-dev libsvn-dev"

