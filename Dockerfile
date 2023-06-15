FROM php:7.4-bullseye

LABEL maintainer="Bastian Kleinschmidt <debaschdi@googlemail.com>" \
      org.label-schema.docker.dockerfile="/Dockerfile" \
      org.label-schema.name="docker.solaranzeige"

ARG BUILD_DEPENDENCIES="build-essential make"

ENV USER_ID="99" \
    GROUP_ID="100" \
    TIMEZONE="Europe/Berlin" \
    UPDATE="yes" \
    MOSQUITTO="yes" \
    DEBIAN_FRONTEND="noninteractive" \
    TERM=xterm \
    LANGUAGE="en_US.UTF-8" \
    LANG="en_US.UTF-8" \
    LC_ALL="en_US.UTF-8"

COPY root/ /

RUN echo "Dir::Cache "";" >> /etc/apt/apt.conf.d/docker-nocache && \
    echo "Dir::Cache::archives "";" >> /etc/apt/apt.conf.d/docker-nocache && \
    echo "path-exclude=/usr/share/locale/*" >> /etc/dpkg/dpkg.cfg.d/docker-nolocales && \
    echo "path-exclude=/usr/share/man/*" >> /etc/dpkg/dpkg.cfg.d/docker-noman && \
    echo "path-exclude=/usr/share/doc/*" >> /etc/dpkg/dpkg.cfg.d/docker-nodoc && \
    echo "path-include=/usr/share/doc/*/copyright" >> /etc/dpkg/dpkg.cfg.d/docker-nodoc
    ### install basic packages
RUN apt-get --quiet --yes --no-install-recommends update && \
    apt-get dist-upgrade --quiet --yes --no-install-recommends && \
    apt-get install --quiet --yes --no-install-recommends \
      apt-utils \
      locales \
      tzdata \
      gnupg2 \
      apt-transport-https \
      sudo \
      cron \
      ${BUILD_DEPENDENCIES} \
      usbutils \
      hwinfo \
      software-properties-common \
      nano \
      sed \
      curl \
      wget \
      git \
      net-tools \
      inetutils-ping \
      python3-pip \
      python3-elementpath \
      python3-protobuf \
      netcdf-bin \
      python3-bs4 \
      python3-requests \
      python3-numpy \
      python3-pandas \
      python3-h5py \
      python3-tables \
      python3-netcdf4 \
      python3-scipy \
      python3-influxdb \
      python3-setuptools \
      python3-astral \
      python3-wheel \
      python3-wrapt \
      python3-yaml \
      python3-isodate \
      ca-certificates
RUN curl -fsSL  http://repo.mosquitto.org/debian/mosquitto-repo.gpg.key | apt-key add - && \
    echo "deb https://repo.mosquitto.org/debian bullseye main" | tee -a /etc/apt/sources.list.d/mosquitto.list && \
    apt-get --quiet --yes --no-install-recommends update && \
    sed -i -e 's/# en_US.UTF-8 UTF-8/en_US.UTF-8 UTF-8/' /etc/locale.gen && \
    locale-gen en_US.UTF-8 && \
    update-locale LANG=en_US.UTF-8 && \
    locale-gen --purge en_US.UTF-8
RUN apt-get install --quiet --yes --no-install-recommends \
      mosquitto \
      mosquitto-clients \
      libmosquitto-dev && \
    pecl install Mosquitto-alpha && \
    echo "extension=mosquitto.so" > /usr/local/etc/php/conf.d/docker-php-ext-mosquitto.ini
RUN python3 -m pip install pysolcast pvlib && \
    chmod +x /usr/local/sbin/entrypoint && \
    chmod +x /usr/local/sbin/solaranzeige-process && \
    chmod +x /usr/local/sbin/solaranzeige-update && \
    chmod +x /usr/local/sbin/solaranzeige-setup && \
    chmod +x /usr/local/sbin/steuerung-setup && \
    chmod +x /usr/local/sbin/pvforecast-update && \
    chmod +x /usr/local/sbin/update && \
    chmod +x /usr/local/sbin/truncate_log && \
    chmod 0644 /etc/cron.d/solaranzeige_cron && \
    crontab /etc/cron.d/solaranzeige_cron && \
    apt-get remove --purge --quiet --yes ${BUILD_DEPENDENCIES} && \
    apt-get -qy autoclean && \
    apt-get -qy clean && \
    apt-get -qy autoremove --purge && \
    rm -rf \
      /tmp/* \
      /var/tmp/* \
      /var/log/* \
      /var/lib/apt/lists/* \
      /var/lib/{apt,dpkg,cache,log}/ \
      /var/cache/apt/archives \
      /usr/share/doc/ \
      /usr/share/man/ \
      /usr/share/locale/

ENTRYPOINT [ "/usr/local/sbin/entrypoint" ]

VOLUME /solaranzeige
VOLUME /pvforecast
VOLUME /var/www

EXPOSE 3000
EXPOSE 80
EXPOSE 1883

