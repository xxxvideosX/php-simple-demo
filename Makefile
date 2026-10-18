
run:
	-composer install
	-php -S localhost:8080 src/index.php

__native-build:
	-composer install
	-vendor/bin/tpc.php src/native_demo.php
