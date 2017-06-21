# Yii2 component for ActiveCollab API
## Install by composer
composer require sem-soft/yii2-activecollab
## Or add this code into require section of your composer.json and then call composer update in console
"sem-soft/yii2-activecollab": "*"
## Usage
In configuration file do
```php
<?php
...
  'components'  =>  [
    ...
    'ac'	=>  [
        'class' => \sem\activecollab\ActiveCollab::className(),
        'companyName'   =>	'Студия Олега Чулакова',
	      'applicationName'	=> 'CHRM',
	      'user'  =>	'user@name.ru',
	      'password'	=>  'password',
	      'acUrl' =>	'url-to-ac-self-hosted'
    ],
    ...
  ],
...
 ?>
 ```
 Use as simple component
 ```php
<?php
	print_r(\Yii::$app->ac->request('projects', [], 'post'));
	print_r(\Yii::$app->ac->requestErrors);
?>
 ```
