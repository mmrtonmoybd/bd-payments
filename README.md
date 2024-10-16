<a href="https://aimeos.org/">
    <img src="https://aimeos.org/fileadmin/template/icons/logo.png" alt="Aimeos logo" title="Aimeos" align="right" height="60" />
</a>

# Aimeos Bangladeshi payment extension

Aimeos Bangladeshi extension for additional payment methods and their service provider.
Some of them may have beta quality and improvements or contributions are always welcome!

## Installation

As every Aimeos extension, the easiest way is to install it via [composer](https://getcomposer.org/). If you don't have composer installed yet, you can execute this string on the command line to download it:
```
php -r "readfile('https://getcomposer.org/installer');" | php -- --filename=composer
```

Add the cache extension name to the "require" section of your ```composer.json``` file:
```
"require": [
    "mmrtonmoybd/bd-payments": "dev-master",
    ...
],
```
You should use a stable release if you don't want to add code or improve the implementation. The available stable versions can be found on [Packagist](https://packagist.org/packages/mmrtonmoybd/bd-payments).

Afterwards you only need to execute the composer update command on the command line:
```
composer update
```

These commands will install the Aimeos extension into the extension directory and it will be available immediately.

## Configuration

Payment options are configured via the shop administration interface in the ["Service" tab](https://aimeos.org/docs/latest/manual/services) and you can add as many payment options as you need to the list for each site. They will be shown on the payment page in the checkout process. In the detail view of a new payment option, you have to enter some values:

![Aimeos payment detail view](https://aimeos.org/docs/latest/manual/Admin-service-omnipay.png)

Make sure you set the status to "enabled" and the type to "Payment". Use an unique code for the payment option, idealy it should be readable and consist only of characters a-z, 0-9 and a few special characters like "-", "_" or ".". The value for the field "Provider" must be the last part of the class name of the payment service provider. Each of the following sections will tell you how it must be named. The last input field influences the position of the payment option within the list of payment options and you should use zero for the top position and greater values for the next payment options.

In the right side of the panel you can add the configuration settings that are specifically required for each payment provider. The list of available settings for each payment provider can be found in the [service documentation](https://aimeos.org/docs/latest/manual/services/#built-in-payment-services).

## License

The Aimeos Bangladeshi payments extension is licensed under the terms of the LGPLv3 Open Source license and is available for free.

## Links
* [Issue tracker](https://github.com/mmrtonmoybd/bd-payments/issues)
* [Source code](https://github.com/mmrtonmoybd/bd-payments)
