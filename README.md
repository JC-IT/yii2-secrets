# Secrets storage and extractor

[![codecov](https://codecov.io/gh/jc-it/yii2-secrets/branch/master/graph/badge.svg)](https://codecov.io/gh/jc-it/yii2-secrets)
[![Continous integration](https://github.com/jc-it/yii2-secrets/actions/workflows/ci.yaml/badge.svg)](https://github.com/jc-it/yii2-secrets/actions/workflows/ci.yaml)
![Packagist Total Downloads](https://img.shields.io/packagist/dt/jc-it/yii2-secrets)
![Packagist Monthly Downloads](https://img.shields.io/packagist/dm/jc-it/yii2-secrets)
![GitHub tag (latest by date)](https://img.shields.io/github/v/tag/jc-it/yii2-secrets)
![Packagist Version](https://img.shields.io/packagist/v/jc-it/yii2-secrets)

This extension provides secret storage and extractor for Yii2. 

## Installation

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```bash
$ composer require jc-it/yii2-secrets
```

or add

```
"jc-it/yii2-secrets": "^<latest version>"
```

to the `require` section of your `composer.json` file.

## Configuration

### Secrets

It is recommended to use this package only in configuration files before your application is loaded, this way they won't
be dumped by your application on chrashes or something unexpected.

```php
$secrets = new \JCIT\secrets\SecretsService(
    new \JCIT\secrets\storages\Chained(
        new \JCIT\secrets\storages\Cache(getenv()),
        new \JCIT\secrets\storages\Json('/run/env.json'),
        new \JCIT\secrets\storages\Filesystem(__DIR__ . '/secrets'),
    )
);
```

Note that the order in the `Chained` storage does matter, wherever a secret is found first that value will be returned.

### Secret extraction

When deploying a new environment it can be a hassle finding out what all secrets are to be configured. This package
contains a console command to extract the secret usages.

- Create an action in a console controller
  ```php
    class SecretsController extends Controller
    {
        public function actions(): array
        {
            return [
                'extract' => [
                    'class' => Extract::class,
                    'calls' => ['$secrets->get', '$secrets->getAndThrowOnNull'],
                    'sourcePath' => '@app/',
                ],
            ];
        }
    }
  ```
- In dependency injection add the storage (which should only be used for the extract command)
  ```php
  ...
  'container' => [
      'definitions' => [
          \JCIT\secrets\interfaces\StorageInterface::class => function() {
              return new \JCIT\secrets\storages\Filesystem(__DIR__ . '/../../../secrets')
          }
      ]
  ],
  ```

## Credits
- [Joey Claessen](https://github.com/joester89)
- [Yii2 Framework](https://github.com/yiisoft/yii2/blob/master/framework/console/controllers/MessageController.php) for the extraction part
