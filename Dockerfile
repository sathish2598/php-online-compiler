FROM ubuntu:22.04

ENV DEBIAN_FRONTEND=noninteractive \
    TZ=UTC

RUN apt-get update \
 && apt-get install -y --no-install-recommends \
      software-properties-common ca-certificates lsb-release apt-transport-https \
      curl gnupg coreutils \
 && add-apt-repository ppa:ondrej/php -y \
 && apt-get update \
 && apt-get install -y --no-install-recommends \
      php8.5-cli php8.5-mbstring php8.5-xml \
      php8.4-cli php8.4-mbstring php8.4-xml \
      php8.3-cli php8.3-mbstring php8.3-xml \
      php8.2-cli php8.2-mbstring php8.2-xml \
 && apt-get remove --purge -y software-properties-common gnupg apt-transport-https \
 && apt-get autoremove -y \
 && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

WORKDIR /app
COPY . /app

# Render injects $PORT (defaults to 10000); the built-in server binds to it.
ENV PORT=10000
EXPOSE 10000

CMD ["sh", "-c", "php8.5 -S 0.0.0.0:${PORT} -t /app"]
