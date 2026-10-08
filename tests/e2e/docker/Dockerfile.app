FROM librebooking/librebooking:develop

# Replace the image's application files with the working tree.
RUN find /var/www/html -mindepth 1 -maxdepth 1 -exec rm -rf {} +
COPY --chown=www-data:root --chmod=0775 . /var/www/html/

RUN bash /usr/local/bin/build_app.sh

# Specs rewrite config.php; pick up changes on the next request.
USER root
RUN echo 'opcache.revalidate_freq=0' > /usr/local/etc/php/conf.d/zz-e2e.ini
USER www-data:root
