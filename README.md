# Setup AirFs

## Prerequisites

- Fresh install of Ubuntu 22.04
- User privileges: root or non-root user with sudo privileges
- Install [AirCms core environment](https://github.com/aircms/core/blob/main/README.md)

## Step 1. Install and setup additional software and modules

### Step 1.1. Install Imagick and PHP Imagick
```console
sudo apt install imagemagick
sudo apt install php-imagick
```
### Step 1.2. Install FFMpeg
```console
sudo apt install ffmpeg
```

### Step 1.2 (Optional). increase the limit on uploaded files
Add the below to the relevant php.ini file (value is related to your purposes, 100M just for example):
```console
upload_max_filesize = 100M
post_max_size = 100M
```

## Step 2. Apache Virtual Hosts
The basic required setup is as follows:
```apacheconf
<VirtualHost *:80>

  ServerName {DOMAIN}
  DocumentRoot {DIRECTORY}/www
  SetEnv AIR_ENV {ENVIRONMENT}
  SetEnv AIR_FS_KEY {KEY}

  <Directory {DIRECTORY}/www>   
    Require all granted
    AllowOverride all
  </Directory>

</VirtualHost>
```
```ENVIRONMENT``` - can be ```dev``` or other value will be considered as production environment (```live```). 
This only affects the configuration file.

```KEY``` - create your key for DEV as a string, remember about security :).

Then we need to restart apache:
```console
systemctl restart apache2
```


## Step 3 (Optional). Install certificate using [Let's Encrypt](https://letsencrypt.org)
### Prerequisites
- A fully registered domain name
- Both of the following DNS records set up for your server
- Completed all previous steps 

### Step 3.1. Installing Certbot
```console
sudo apt install certbot python3-certbot-apache
```

### Step 3.2. Obtaining an SSL Certificate
```console
sudo certbot --apache
```
```console
Which names would you like to activate HTTPS for?
- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
1: {DOMAIN}
...
- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
Select the appropriate numbers separated by commas and/or spaces, or leave input
blank to select all options shown (Enter 'c' to cancel):
```

This step will prompt you to inform Certbot of which domains you’d like to activate ```HTTPS``` for. 
The listed domain names are automatically obtained from your Apache virtual host configuration, 
that’s why it’s important to make sure you have the correct ```ServerName``` and ```ServerAlias``` settings 
configured in your virtual host. If you’d like to enable ```HTTPS``` for all listed domain names (recommended), 
you can leave the prompt blank and hit ```ENTER``` to proceed. Otherwise, select the domains you want to enable 
```HTTPS``` for by listing each appropriate number, separated by commas and/ or spaces, then hit ```ENTER```.

You’ll see output like this:
```console
Requesting a certificate for {DOMAIN}

Successfully received certificate.
Certificate is saved at: /etc/letsencrypt/live/{DOMAIN}/fullchain.pem
Key is saved at:         /etc/letsencrypt/live/{DOMAIN}/privkey.pem
This certificate expires on 2024-06-21.
These files will be updated when the certificate renews.
Certbot has set up a scheduled task to automatically renew this certificate in the background.

Deploying certificate
Successfully deployed certificate for {DOMAIN} to /etc/apache2/sites-enabled/{DOMAIN}-le-ssl.conf
Congratulations! You have successfully enabled HTTPS on https://{{DOMAIN}

- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
If you like Certbot, please consider supporting our work by:
 * Donating to ISRG / Let's Encrypt:   https://letsencrypt.org/donate
 * Donating to EFF:                    https://eff.org/donate-le
- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
```

### Step 3.3. Verifying Certbot Auto-Renewal
Let’s Encrypt’s certificates are only valid for ninety days. This is to encourage users to automate their certificate 
renewal process, as well as to ensure that misused certificates or stolen keys will expire sooner rather than later.

The certbot package we installed takes care of renewals by including a renew script to /etc/cron.d, which is 
managed by a systemctl service called certbot.timer. This script runs twice a day and will automatically renew any
certificate that’s within thirty days of expiration.

To check the status of this service and make sure it’s active and running, you can use:

```console
sudo systemctl status certbot.timer
```

You’ll get output similar to this:

```console
● certbot.timer - Run certbot twice daily
     Loaded: loaded (/lib/systemd/system/certbot.timer; enabled; vendor preset: enabled)
     Active: active (waiting) since Sat 2024-03-23 17:30:07 UTC; 10min ago
    Trigger: Sun 2024-03-24 05:23:32 UTC; 11h left
   Triggers: ● certbot.service

Mar 23 17:30:07 live systemd[1]: Started Run certbot twice daily.
```

To test the renewal process, you can do a dry run with certbot:

```console
sudo certbot renew --dry-run
```

You’ll get output similar to this:
```console
- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
Processing /etc/letsencrypt/renewal/{DOMAIN}.conf
- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
Account registered.
Simulating renewal of an existing certificate for {DOMAIN}

- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
Congratulations, all simulated renewals succeeded:
  /etc/letsencrypt/live/{DOMAIN}/fullchain.pem (success)
- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
```

## Step 4. Install project
### Step 4.1. Clone project sources
```console
cd /var/www
git clone https://github.com/aircms/fs.git
```

### Step 4.2. Install composer dependencies
```console
composer install
```

### Step 4.3. Prepare storage folder 
```console
composer run-script storage
```

### Step 4.4. Prepare assets
```console
composer run-script assets
```

### Step 4.5. Create key for environments  
Based on your environment, open the appropriate file in the ```./config/``` folder and set the value to the ```key``` element.

## Step 5. Open project in your browser
Navigate to ```{DOMAIN}?key={KEY}```

```DOMAIN``` - It is your domain.

```KEY``` - it is your created key, (Step 4.4).