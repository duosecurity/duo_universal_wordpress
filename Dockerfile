ARG wordpress_version

FROM wordpress:$wordpress_version
RUN curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar && chmod +x wp-cli.phar && mv wp-cli.phar /usr/local/bin/wp
RUN apt-get update
RUN apt-get -y install vim less
