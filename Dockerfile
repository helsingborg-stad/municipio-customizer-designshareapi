FROM webdevops/php-nginx:8.2-alpine

ENV WEB_DOCUMENT_ROOT=/app/public

COPY . /app/

# Laravel must write its compiled views, cache and logs at runtime. The shared
# design JSON files live under storage/app and are mounted from a PVC in
# Kubernetes; the rest of storage remains writable in the container.
RUN mkdir -p /app/storage/app/themes /app/storage/framework/cache/data \
    /app/storage/framework/sessions /app/storage/framework/views /app/storage/logs \
    && chown -R 1000:1000 /app/storage /app/bootstrap/cache
