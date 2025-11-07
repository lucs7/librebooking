ARG BASE_IMAGE=librebooking/librebooking:develop
FROM ${BASE_IMAGE}
ENV DEBIAN_FRONTEND=noninteractive

# Configure PHP memory and Xdebug defaults for development
RUN set -eux; \
  echo 'memory_limit = 2048M' > /usr/local/etc/php/conf.d/docker-php-memlimit.ini; \
  { \
  echo 'xdebug.mode=debug,develop'; \
  echo 'xdebug.start_with_request=yes'; \
  echo 'xdebug.discover_client_host=1'; \
  echo 'xdebug.log_level=0'; \
  } > /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

WORKDIR /
VOLUME /config

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["apache2-foreground"]
