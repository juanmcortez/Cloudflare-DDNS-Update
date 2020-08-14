# Cloudflare-DDNS-Update

This project was born due to the necessity of having a webserver hosted locally
and managing it's domains DNS ip's via Cloudflare DNS services.

Your dynamic ip's will never be an issue again if you use this script aside
Cloudflare DNS services.

**Both are free!**

Once you have set-up the script and enable the service from Cloudflare, the only
thing missing in your website is a free SSL certificate and that's it! You will
have a secure site with really low cost!


# Table of contents
- [Cloudflare DNS Service](#cloudflare-dns-service)
- [Fast install](#fast-install)
- [Step-by-Step install](#step-by-step-install)
- [Security](#security)
- [Contributing](#contributing)
- [Contact](#contact)
- [Support](#support)
- [License](#license)


# Cloudflare DNS Service

We are going to use Cloudflare's API v4 system to keep our records updated. If in
the future the API evolves or we decide to change things radically, we will create
a new repository for the script. This way, we can keep retro compatibility.


# Fast install

The script can be easy installed, by just cloning the project or downloading the
release files as a zip.

- Go to the server's user home folder and install the script there.

- Once done, edit the .env file with the credentials required.

- Create a cron job that points to the script and calls it every 15 mins.

That's it!


# Step-by-Step install

See our [doc's page](https://github.com/juanmcortez/Cloudflare-DDNS-Update/blob/master/CloudflareDDNS/docs/STEPBYSTEP.md) for details.


# Security

See our [security policy](https://github.com/juanmcortez/Cloudflare-DDNS-Update/blob/master/SECURITY.md) for details


# Contributing

See our [contribution page](https://github.com/juanmcortez/Cloudflare-DDNS-Update/blob/master/CloudflareDDNS/docs/CONTRIBUTING.md) for details.


# Contact

See our [contact page](https://github.com/juanmcortez/Cloudflare-DDNS-Update/blob/master/CloudflareDDNS/docs/CONTACT.md) for details.


# Support

See our [support page](https://github.com/juanmcortez/Cloudflare-DDNS-Update/blob/master/CloudflareDDNS/docs/SUPPORT.md) for details.

# License

Licensed under the GNU General Public License v3.0. See the [LICENSE](https://github.com/juanmcortez/Cloudflare-DDNS-Update/blob/master/LICENSE) file for details.
