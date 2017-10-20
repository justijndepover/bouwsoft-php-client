# Bouwsoft - Laravel Connection
this package is able to make a solid connection with the Bouwsoft USE IT API.

## Installation
Add the following to your list of Service Providers inside `config/app.php`

```php
JustijnDepover\BouwsoftPhpClient\Providers\BouwsoftServiceProvider::class,
```

## Usage

The step above will create a Singleton. Access it with the following code:
```php
$connection = app()->make('Bouwsoft\Connection');
```

To access content from the Bouwsoft API (e.g. Addresses) do the following:
```php
$addresses = $connection->get('Addresses');
```

## Limitations

The Bouwsoft API

## Contributing

At the time of writing I only had to access content, not write back to the API.
If you want you can contribute by creating a fork and opening a pull request.