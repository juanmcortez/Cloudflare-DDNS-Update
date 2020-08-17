# Step by Step Guide

## Requirements

 - SSH access to your server.
 - Git installed or access to home folder via FTP.
 - PHP access via cli.
 - Cron access.
 - User writing permissions on the server.
 - Cloudflare DNS access.

## Steps

FTP or SSH to your server:

```
user@computer:~ $ ssh user@<server-ip>
```

Once logged in, make sure you have access php on the cli:

```
user@<server-ip>:~ $ php -v
PHP 7.3.19-1~deb10u1 (cli) (built: Jul  5 2020 06:46:45) ( NTS )
Copyright (c) 1997-2018 The PHP Group
Zend Engine v3.3.19, Copyright (c) 1998-2018 Zend Technologies
    with Zend OPcache v7.3.19-1~deb10u1, Copyright (c) 1999-2018, by Zend Technologies

user@<server-ip>:~ $
```

**Awesome!** check if git is installed:

```
user@<server-ip>:~ $ git --version
git version 2.20.1

user@<server-ip>:~ $
```

**Great!** we can now create a folder in the user home to clone / copy the project and have it running really fast:

```
user@<server-ip>:~ $ mkdir cloudflare-ddns-update

user@<server-ip>:~ $ cd cloudflare-ddns-update/

user@<server-ip>:~/cloudflare-ddns-update $
```

Download the latest version of the project via the [releases page](https://github.com/juanmcortez/Cloudflare-DDNS-Update/releases) and upload it's content via FTP to the newly created folder - OR - clone the project via Git:

```
user@<server-ip>:~/cloudflare-ddns-update $ git clone git@github.com:juanmcortez/Cloudflare-DDNS-Update.git .
Cloning into '.'...

```

The dot at the end of the clone command is required to clone the project inside the folder you are on.

Once the project has been cloned, we need to set up settings, and to do this, we need to run:

```
user@<server-ip>:~/cloudflare-ddns-update $ mv .env.example .env
user@<server-ip>:~/cloudflare-ddns-update $ nano .env
```

After running the commands we get access to the settings file and follow the instructions on it to fill it up:

![Filling settings values](https://github.com/juanmcortez/Cloudflare-DDNS-Update/blob/master/CloudflareDDNS/docs/images/step1.jpg)

To fill the values for the dns items, go to your Cloudflare dashboard for each of the domains:

![Get values from cloudflare](https://github.com/juanmcortez/Cloudflare-DDNS-Update/blob/master/CloudflareDDNS/docs/images/step2.jpg)

Once you completed all the data in the env file, save it and create a cron job that will point to the dns.update.php file.

```
user@<server-ip>:~/cloudflare-ddns-update $ crontab -e
```

Add the following line to it, replacing < user > with you server username:

```
*/15 * * * * php /home/<user>/cloudflare-ddns-update/dns.update.php
```

Like this:

![Cron](https://github.com/juanmcortez/Cloudflare-DDNS-Update/blob/master/CloudflareDDNS/docs/images/step3.jpg)

Save the file, and that's it!
