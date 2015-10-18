# Symfony powered WordPress scaffolding

This project uses the power of Symfony and WP-CLI to scaffold a new WordPress project via terminal.

## Setup

In your home folder /Users/Ash for example, you can create a .bash_profile. In here you can export variables and then add them to your path.
Open up the file to edit it in your favourite editor and add in your PHP path

```
export MAMP_PHP=/Applications/MAMP/bin/php/php5.6.10/bin
export PATH="$MAMP_PHP:$PATH"
export PATH=$PATH:/Applications/MAMP/Library/bin/
```

Now unzip this application to your htdocs folder and then `cd` into it via terminal

Run `composer install` and then `chmod +x console.php` to enable execution of the file.

Run `./console.php setup` to begin WordPress scaffolding!