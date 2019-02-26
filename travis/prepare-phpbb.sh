#!/bin/bash
# @copyright (c) Josh Bialkowski <josh.bialkowski@gmail.com>
# @license GNU General Public License, version 2 (GPL-2.0)

set -e
set -x

EXTNAME=$1
BRANCH=$2
DB=$3
TRAVIS_PHP_VERSION=$4

# Copy extension to a temp folder
mkdir ../../tmp
cp -R . ../../tmp
cd ../../

# Clone phpBB
git clone --depth=1 "git://github.com/phpbb/phpbb.git" "phpBB3" --branch=$BRANCH

# ------------------------------------------------------------------------------
# phpBB3/travis/prepare-extension.sh
# ------------------------------------------------------------------------------

# Move the extension in place
mkdir --parents phpBB3/phpBB/ext/$EXTNAME
cp -R tmp/* phpBB3/phpBB/ext/$EXTNAME

# ------------------------------------------------------------------------------
# phpBB3/travis/setup-phpbb.sh
# ------------------------------------------------------------------------------

if [ "$DB" == "mariadb" ]
then
	phpBB3/travis/setup-mariadb.sh
fi

phpBB3/travis/setup-php-extensions.sh

# ------------------------------------------------------------------------------
# phpBB3/travis/setup-webserver.sh
# ------------------------------------------------------------------------------

sudo apt-get update
sudo apt-get install -y nginx realpath
sudo service nginx stop

DIR=$(realpath ${PWD})
USER=$(whoami)
PHPBB_ROOT_PATH=$(realpath "phpBB3/phpBB")
NGINX_SITE_CONF="/etc/nginx/sites-enabled/default"
NGINX_CONF="/etc/nginx/nginx.conf"
APP_SOCK=${DIR}/php-app.sock

# php-fpm
PHP_FPM_BIN="$HOME/.phpenv/versions/$TRAVIS_PHP_VERSION/sbin/php-fpm"
PHP_FPM_CONF="$DIR/php-fpm.conf"

echo "
	[global]

	[travis]
	user = $USER
	group = $USER
	listen = $APP_SOCK
	listen.mode = 0666
	pm = static
	pm.max_children = 2

	php_admin_value[memory_limit] = 128M
" > $PHP_FPM_CONF

sudo $PHP_FPM_BIN \
	--fpm-config "$DIR/php-fpm.conf"

# nginx
cat $PWD/tmp/travis/nginx.sample.conf \
| sed "s/root \/path\/to\/phpbb/root $(echo $PHPBB_ROOT_PATH | sed -e 's/\\/\\\\/g' -e 's/\//\\\//g' -e 's/&/\\\&/g')/g" \
| sed -e '1,/The actual board domain/d' \
| sed -e '/If running php as fastcgi/,$d' \
| sed -e "s/fastcgi_pass php;/fastcgi_pass unix:$(echo $APP_SOCK | sed -e 's/\\/\\\\/g' -e 's/\//\\\//g' -e 's/&/\\\&/g');/g" \
| sed -e 's/#listen 80/listen 80/' \
| sudo tee $NGINX_SITE_CONF
sudo sed -i "s/user www-data;/user $USER;/g" $NGINX_CONF

sudo service nginx start

cd phpBB3/phpBB
php ../composer.phar install --dev --no-interaction
cd ..
