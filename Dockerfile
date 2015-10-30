FROM hackingdata:base
ADD ./nginx-site.conf /etc/nginx/sites-available/default
EXPOSE 80
WORKDIR /app

ADD ./nginx.conf /etc/nginx/nginx.conf
ADD ./startup.sh /app/startup.sh
ADD ./public/wp-config.php /app/public.built/wp-config.php
CMD ["/bin/bash","/app/startup.sh"]
