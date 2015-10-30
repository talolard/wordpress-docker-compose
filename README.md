WP docker-compose
=========
This is a template for deploying Wordpress with docker-compose. It is largely based off of [xyu/heroku-wp](http://github.com/xyu/heroku-wp) with some extra input from the team at [HackingData](http://www.hackingdata.com) and [HackingUI](http://www.hackingdata.com).    
I made this because Virtualbox and Vagrant frustrate me while dockerized things fill me with a deep sense of Zen. Also, my friends at [HackingUI](http://www.hackingdata.com) wanted a simpler way to develop locally.

QuickStart
====================
* [Install docker-compose](https://docs.docker.com/compose/install/)
* Clone this repository    
* run
```bash
 docker-compose up
```
    
Adding your own themes
======================
Open the docker-compose.yml file
It should look like this
```yml
web:
  image: talolard/easy-wordpress
  ports:
    - "80:80"
  links:
    - mysql
    - memcache
mysql:
  image: orchardup/mysql
  ports:
    - "3306:3306"
memcache:
  image: memcached
  environment:
    MYSQL_DATABASE: wordpress
```
In the web section, under the links line add the following
```yml
web:
  image: talolard/easy-wordpress
  ports:
    - "80:80"
  links:
    - mysql
    - memcache
  volumes:
    - /path/to/wp-content/themes:/app/public.built/wp-content/themes
```
This will let you modify locally and see changes live.
Building your own container
===========================
Once you are happy with the changes you made and you want to include the theme in your own docker image do the following
```bash
docker build -t tagforyourimage ./
```
To test it, modify the docker-compose.yml, so that
```yml
web:
  image: talolard/easy-wordpress
```
becomes
```yml
web:
  image: tagforyourimage
```

Whats in the box?
=================
This is largely do to [xyu/heroku-wp](http://github.com/xyu/heroku-wp), I just modified the configs a bit so that they would play nicce with docker.
The repository is built on top of the following technologies.
* [nginx](http://nginx.org) - For serving web content.
* [HHVM](http://hhvm.com) - A virtual machine designed to serve Hack and PHP.
* [MySQL](http://www.mysql.com) - Provided by the ClearDB add-on.
* [Memcached](http://memcached.org) - Provided by the MemCachier add-on.
* [Composer](https://getcomposer.org) - A dependency manager to make installing and managing plugins easier.

In additon repository comes bundled with the following plugins.
* [SASL object cache](https://github.com/xyu/SASL-object-cache) - For running with MemCachier add-on
* [Batcache](http://wordpress.org/plugins/batcache/) - For full page output caching
* [SSL Domain Alias](http://wordpress.stackexchange.com/questions/38902) - For sending SSLed traffic to a different domain (needed to send WP admin traffic to Heroku over SSL directly.)
* [Authy Two Factor Auth](https://www.authy.com/products/wordpress)
* [Jetpack](http://jetpack.me/)
* [SendGrid](http://wordpress.org/plugins/sendgrid-email-delivery-simplified/)
* [WP Read-Only](http://wordpress.org/extend/plugins/wpro/)

WordPress and most included plugins are installed by Composer on build. To add new plugins or upgrade versions of plugins simply update the `composer.json` file and then generate the `composer.lock` file with the following command locally:

```bash
$ composer update --ignore-platform-reqs
```

To customize the site simply place files into `/public` which upon deploy to Heroku will be copied on top of the standard WordPress install and plugins specified by Composer.

Upcoming plans
====
* Add Support for deployment on Heroku and AWS (Beanstalk?)
* Make it easy to change MySQL and MemCache servers
* Test SSl