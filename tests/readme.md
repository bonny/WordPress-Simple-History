# Tests

## Run images

To run image with tag `eskapism/phpunit-wordpress-plugin:wp5.2-php7.2` use argument `DOCKER_IMAGE_PHPUNIT` and set it to the image you want to run:

`$ DOCKER_IMAGE_PHPUNIT=eskapism/phpunit-wordpress-plugin:wp5.2-php7.2 docker-compose -f tests/docker-compose.yml run phpunit phpunit --testdox`

Available tags:

- `eskapism/phpunit-wordpress-plugin:wp5.2-php5.6`
- `eskapism/phpunit-wordpress-plugin:wp5.2-php7.2`
- `eskapism/phpunit-wordpress-plugin:wp5.6.2-php7.2`
- `eskapism/phpunit-wordpress-plugin:wp5.6.2-php7.4.15`

Tags can also be seen at Docker Hub:
https://hub.docker.com/r/eskapism/phpunit-wordpress-plugin

Remember that the tags from the original project is also available, for example:

- `futureys/phpunit-wordpress-plugin:5.5.0-php7.4.8-apache-buster`
- `futureys/phpunit-wordpress-plugin:5.4.2-php7.3.19-apache-buster`
- `futureys/phpunit-wordpress-plugin:4.3.22-php7.1.33-apache-buster`

## Build images

In folder `tests`:

```
$ docker build \
  --build-arg PHP_IMAGE_TAG=7.2-apache-buster \
  --build-arg WORDPRESS_VERSION=5.2.9 \
  --tag eskapism/phpunit-wordpress-plugin:wp5.2-php7.2 \
  .
```

```
 $ docker build \
  --build-arg PHP_IMAGE_TAG=7.2-apache-buster \
  --build-arg WORDPRESS_VERSION=5.6.2 \
  --tag eskapism/phpunit-wordpress-plugin:wp5.6.2-php7.2 \
  .
```

```
 $ docker build \
  --build-arg PHP_IMAGE_TAG=7.4.15-apache-buster \
  --build-arg WORDPRESS_VERSION=5.6.2 \
  --tag eskapism/phpunit-wordpress-plugin:wp5.6.2-php7.4.15 \
  .
```

```
 $ docker build \
  --build-arg PHP_IMAGE_TAG=5.6.40-apache \
  --build-arg WORDPRESS_VERSION=5.2 \
  --tag eskapism/phpunit-wordpress-plugin:wp5.2-php5.6 \
  .
```

Set `PHP_IMAGE_TAG` and `WORDPRESS_VERSION` to wanted versions and set the tag parameter to match those.

## Push tag to Docker Hub

To push a new tag to this repository,

`docker push eskapism/phpunit-wordpress-plugin:tagname`,
for example
`docker push eskapism/phpunit-wordpress-plugin:wp5.2-php7.2`.
