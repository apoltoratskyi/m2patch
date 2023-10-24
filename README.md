# M2 patch generation tool

## Installation
```sh
git clone git@github.com:apoltoratskyi/m2patch.git m2patch
cd m2patch
copy .env.example to .env
add your credentials to .env
open file and configure your creds to jira and github api token
chmod +x m2patch.php
ln -s ./m2patch.php ../m2patch.php
```

## Usage:
```sh
 php m2patch.php ACSD-6661 
 php m2patch.php ACSD-6661 _v2
 php m2patch.php ACSD-6661 _DEBUG
```
