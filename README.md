# Octane gRPC

[![Latest Version on Packagist](https://img.shields.io/packagist/v/mosamirzz/octane-grpc.svg?style=flat-square)](https://packagist.org/packages/mosamirzz/octane-grpc)
[![Total Downloads](https://img.shields.io/packagist/dt/mosamirzz/octane-grpc.svg?style=flat-square)](https://packagist.org/packages/mosamirzz/octane-grpc)

Add support for gRPC server in laravel octane.

## Installation

You can install the package via composer:

```bash
composer require mosamirzz/octane-grpc
```

## Usage

1. add the following at the end of your octane.php config file.
```php
return [
    // ... 

    /*
    |--------------------------------------------------------------------------
    | gRPC Server
    |--------------------------------------------------------------------------
    |
    | The following setting used by the swoole gRPC server.
    |
    */

    'grpc' => [
        'host' => '0.0.0.0',
        'port' => 50051,
        'mode' => OpenSwoole\Http\Server::POOL_MODE,
        'services_path' => base_path('routes/grpc.php'),
    ],
];

```

2. create the routes file that will contains all the services that needs to be registered in the server.
```bash
touch routes/grpc.php
```
and the content of the file will be like:
```php
<?php

use Proto\Greeter\GreeterService;

return [
    // GreeterService::class => new GreeterService(),
];
```
3. add the following to composer.json:
```json
{
    // ...
    "autoload": {
        "psr-4": {
            // ...
            "Proto\\": "Proto/"
        }
    }
    // ...
}
```
then run this command
```bash
composer dump-autoload -o
```

4. run the server
```bash
sail artisan octane:swoole-grpc
```

## requirements
- `openswoole` php extension
- `protoc`
- `protoc-gen-openswoole-grpc` plugin (to generate grpc serevr stubs)
- `protoc-gen-php-grpc` plugin (to generate grpc client stubs)

1. download `openswoole` extension.

```bash
# first: install needed libs
sudo apt-get install -y libcurl4-openssl-dev openssl libssl-dev libpcre3-dev build-essential php8.3-common

# download & install the extension
git clone https://github.com/openswoole/ext-openswoole.git
cd ext-openswoole
git checkout v22.1.2 # choose the version you want
phpize
./configure --enable-openssl --enable-http2 --enable-hook-curl
sudo make && sudo make install

# create openswoole.ini && write the following in the file
sudo echo "; priority=20" >> /etc/php/8.3/mods-available/openswoole.ini
sudo echo "extension=openswoole.so" >> /etc/php/8.3/mods-available/openswoole.ini

# enable the extension
sudo phpenmod openswoole

# verify it is installed
php -m | grep openswoole
```

2. download `protoc` 

you can install the release you want from this page: [Protobuf releases](https://github.com/protocolbuffers/protobuf/releases)

```bash
# download the zip file
curl -LO https://github.com/protocolbuffers/protobuf/releases/download/v29.0-rc1/protoc-29.0-rc-1-linux-x86_64.zip

# extract it
unzip protoc-29.0-rc-1-linux-x86_64.zip -d protoc

# move the binary to the /usr/bin directory to be used from anywhere
sudo mv protoc/bin/protoc /usr/bin

# remove unwanted files
rm protoc-29.0-rc-1-linux-x86_64.zip
rm -r protoc
```

3. download the `protoc-gen-openswoole-grpc`

you can install the release you want from this page: [OpenSwoole plugin releases](https://github.com/openswoole/protoc-gen-openswoole-grpc/releases)

```bash
# download the zip file
curl -LO https://github.com/openswoole/protoc-gen-openswoole-grpc/releases/download/0.1.1/protoc-gen-openswoole-grpc-0.1.1-darwin-amd64.tar.gz

# extract it
mkdir openswoole && tar -xvzf protoc-gen-openswoole-grpc-0.1.1-darwin-amd64.tar.gz -C openswoole

# move the binary to the /usr/bin directory to be used from anywhere
sudo mv openswoole/protoc-gen-openswoole-grpc /usr/bin

# remove unwanted files
rm protoc-gen-openswoole-grpc-0.1.1-darwin-amd64.tar.gz
rm -r openswoole
```

4. download the `protoc-gen-php-grpc`

```bash
# you will clone the grpc repository but you must replace the RELEASE_TAG_HERE with the tag version you want to install
git clone -b RELEASE_TAG_HERE https://github.com/grpc/grpc

# e.g. download tag v1.67.0
git clone -b v1.67.0 https://github.com/grpc/grpc
cd grpc
git submodule update --init
mkdir -p cmake/build
cd cmake/build
cmake ../..
make protoc grpc_php_plugin

# now mv the grpc_php_plugin into /usr/bin but rename it to protoc-gen-php-grpc
sudo mv grpc_php_plugin /usr/bin/protoc-gen-php-grpc

# you can remove unwanted directories like above
```

## server example

1. Create a `Proto/greeter.proto`
```proto
syntax = "proto3";

package greeter;

option php_namespace = "Proto\\Greeter";
option php_metadata_namespace = "Proto\\Greeter\\Metadata";

service Greeter {
    rpc SayHello(HelloRequest) returns (HelloReply) {}
}

message HelloRequest {
    string name = 1;
}

message HelloReply {
    string message = 1;
}
```

2. generate the code for the server
```bash
protoc --php_out=. --openswoole-grpc_out=. Proto/greeter.proto
```
the command above will generate the following in your currenct directory

```
Proto/
└── Greeter/
    ├── Metadata/
    │   └── Greeter.php
    ├── GreeterClient.php
    ├── GreeterInterface.php
    ├── GreeterService.php
    ├── HelloReply.php
    └── HelloRequest.php
```

3. write the service implementation

you will need to write the business login inside the generated service `GreeterService.php` it will be like the following:
```php
<?php declare(strict_types=1);

namespace Proto\Greeter;

use OpenSwoole\GRPC;

class GreeterService implements GreeterInterface
{
    /**
    * @param GRPC\ContextInterface $ctx
    * @param HelloRequest $request
    * @return HelloReply
    *
    * @throws GRPC\Exception\InvokeException
    */
    public function SayHello(GRPC\ContextInterface $ctx, HelloRequest $request): HelloReply
    {
        // 1. get the request data
    	$name = $request->getName();

        // 2. write any business logic here
        $message = "Hello " . $name;

        // 3. build the response object
        $response = new HelloReply();
        $response->setMessage($message);

        // 4. return the response
        return $response;
    }
}
```

4. now register the `GreeterService` in the gRPC server.

open `routes/grpc.php` and add the following:
```php
<?php

use Proto\Greeter\GreeterService;

return [
    GreeterService::class => new GreeterService(),
];
```

5. run the gRPC server
```bash
sail artisan octane:swoole-grpc
```

## client example
first of all you need to install `grpc` extension

you can download it with
```bash
sudo apt-get install php8.3-grpc

# or
pecl install grpc
# then add grpc.so into php.ini file
```

1. install `grpc/grpc` package
```bash
composer require grpc/grpc
```

2. generate the php code from `greeter.proto` file for the client.
```bash
protoc --php_out=. --php-grpc_out=. Proto/greeter.proto
```
it will generate the files like:
```
Proto/
└── Greeter/
    ├── Metadata/
    │   └── Greeter.php
    ├── GreeterClient.php
    ├── HelloReply.php
    └── HelloRequest.php
```

3. create a `client.php` file
```php
<?php

use Proto\Greeter\GreeterClient;
use Proto\Greeter\HelloRequest;

require __DIR__ . '/vendor/autoload.php';

// connect to the Greeter service
$client = new GreeterClient("0.0.0.0:50051", [
    'credentials' => Grpc\ChannelCredentials::createInsecure(),
]);

// build the request data
$request = new HelloRequest();
$request->setName("Mohamed Samir");

// send the request and wait for the response
/** @var Proto\Greeter\HelloReply $response */
[$response, $status] = $client->SayHello($request)->wait();

// check the status code of the response and get the error details
if ($status->code !== Grpc\STATUS_OK) {
    echo "ERROR: " . $status->code . ", " . $status->details . PHP_EOL;
    exit(1);
}

// do anything with the response
var_dump($response->getMessage());
```

### Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

### Security

If you discover any security related issues, please email gm.mohamedsamir@gmail.com instead of using the issue tracker.

## Credits

-   [Mohamed Samir](https://github.com/mosamirzz)
-   [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Laravel Package Boilerplate

This package was generated using the [Laravel Package Boilerplate](https://laravelpackageboilerplate.com).
