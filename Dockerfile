FROM ubuntu:24.04
LABEL maintainer="zsx<zsx@zsxsoft.com>"
ENV NODEJS_VERSION v22.18.0
ENV DEBIAN_FRONTEND=noninteractive

ARG location
RUN export NODEJS_HOST=https://nodejs.org/dist/; if [ "x$location" = "xchina" ]; then echo "Changed Ubuntu source"; sed -i 's#http://archive\.ubuntu\.com/ubuntu/#https://mirrors.tuna.tsinghua.edu.cn/ubuntu/#g' /etc/apt/sources.list.d/ubuntu.sources 2>/dev/null || sed -i 's#http://archive\.ubuntu\.com/ubuntu/#https://mirrors.tuna.tsinghua.edu.cn/ubuntu/#g' /etc/apt/sources.list; export NPM_CONFIG_REGISTRY=https://registry.npmmirror.com; export ELECTRON_MIRROR=https://cdn.npmmirror.com/binaries/electron/; export NODEJS_HOST=https://cdn.npmmirror.com/binaries/node/; fi; \
    \
    mkdir /data/ /data/logs/ /data/logs/nginx /data/www/ /data/tools /www/ \
    && mkdir /zbp-app-validator \
    && apt-get update \
    && apt-get -y install software-properties-common \
# Base
    && apt-get -y install git curl wget iptables unzip sudo \
# Fonts (Chinese)
    && apt-get install -y --no-install-recommends fonts-noto fonts-noto-cjk fonts-noto-color-emoji \
# nginx & PHP
    && LC_ALL=C.UTF-8 add-apt-repository ppa:ondrej/php \
    && if [ "x$location" = "xchina" ]; then echo "Changed Ubuntu source"; find /etc/apt/sources.list.d/ -type f -name "*.list" -exec  sed  -i.bak -r  's#deb(-src)?\s*http(s)?://ppa.launchpad.net#deb\1 https://launchpad.proxy.ustclug.org#ig' {} \; ;fi \
    && apt-get update \
    && apt-get -y install nginx php8.2-fpm php8.2-gd php8.2-curl php8.2-mysql php8.2-cli php8.2-xml php8.2-mbstring php8.2-dev php8.2-sqlite3 php8.2-zip php-pear \
    && pecl install uopz \
    && rm -rf /etc/nginx/sites-enabled/default \
    && curl https://getcomposer.org/installer | php -- --filename=composer \
    && chmod a+x composer \
    && mv composer /usr/local/bin/composer \
    && (echo extension=uopz.so > /etc/php/8.2/mods-available/uopz.ini) \
    && (echo extension=uopz.so > /etc/php/8.2/fpm/conf.d/uopz.ini) \
    && (echo extension=uopz.so > /etc/php/8.2/cli/conf.d/uopz.ini) \
    && rm -rf /tmp/pear \
# Nodejs
    && wget "$NODEJS_HOST/$NODEJS_VERSION/node-$NODEJS_VERSION-linux-x64.tar.xz" -O/tmp/node.tar.xz \
    && tar -C /usr/local/ -xvf /tmp/node.tar.xz --strip-components 1 \
# Java
    && apt-get -y install default-jre-headless \
# Chromium
    && apt-get -y install libgtk-3-0 libnss3 libnss3-tools libasound2t64 libxss1 \
# MySQL
    && bash -c "debconf-set-selections <<< 'mysql-server mysql-server/root_password password rootpassword'" \
    && bash -c "debconf-set-selections <<< 'mysql-server mysql-server/root_password_again password rootpassword'" \
    && apt-get -y install mysql-server \
    && mkdir /var/run/mysqld \
    && chown -R mysql:mysql /var/lib/mysql /var/run/mysqld \
# Clean rubbish
    && apt-get -y remove php8.2-dev php-pear \
    && apt-get -y autoremove \
    && apt-get autoclean \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/*

COPY env/* /tmp/
# mitmproxy
RUN if [ ! -f /tmp/mitmproxy.tar.gz ]; then wget https://github.com/mitmproxy/mitmproxy/releases/download/v4.0.1/mitmproxy-4.0.1-linux.tar.gz -O/tmp/mitmproxy.tar.gz; fi\
    && tar -C /usr/local/bin -xvf /tmp/mitmproxy.tar.gz \
    && chmod 0777 /usr/local/bin/mitmdump /usr/local/bin/mitmproxy /usr/local/bin/mitmweb \
    && sysctl -w net.ipv4.ip_forward=1

RUN mkdir /data/certs \
    && ln -s /data/certs /root/.mitmproxy \
    && timeout 5s mitmdump || true \
    && cp /root/.mitmproxy/mitmproxy-ca-cert.pem /usr/local/share/ca-certificates/mitmproxy.crt \
    && update-ca-certificates --fresh

COPY ./docker-scripts/64-language-selector-prefer.conf /etc/fonts/conf.d/64-language-selector-prefer.conf
RUN fc-cache -fv

COPY package.json package-lock.json composer.json composer.lock /zbp-app-validator/
WORKDIR /zbp-app-validator/

RUN if [ "x$location" = "xchina" ]; then composer config -g repo.packagist composer https://mirrors.aliyun.com/composer/; export NPM_CONFIG_REGISTRY=https://registry.npmmirror.com; export ELECTRON_MIRROR=https://cdn.npmmirror.com/binaries/electron/; export PUPPETEER_DOWNLOAD_BASE_URL=https://cdn.npmmirror.com/binaries/chrome-for-testing; fi; \
    npm install && npm cache clean --force && composer install && composer clearcache

COPY ./ /zbp-app-validator/
RUN chmod 0777 /zbp-app-validator/docker-scripts/* \
    && bash /zbp-app-validator/docker-scripts/docker-init.sh \
    && bash /zbp-app-validator/docker-scripts/create-site.sh local zblogphp.local

ENV HOME=/data/www/local/home

ENTRYPOINT ["/zbp-app-validator/docker-scripts/run.sh"]
