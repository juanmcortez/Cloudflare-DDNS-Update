# Cloudflare-DDNS-Update

This project was born due to the necessity of having a webserver hosted locally
and managing it's domains DNS ip's via Cloudflare DNS services.

Your dynamic ip's will never be an issue again if you use this script aside
Cloudflare DNS services.

**Both are free!**

Once you have set-up the script and enable the service from Cloudflare, the only
thing missing in your website is a free SSL certificate and that's it! You will
have a secure site with really low cost!

![Maintenance](https://img.shields.io/maintenance/yes/2020)
![GitHub Release Date](https://img.shields.io/github/release-date/juanmcortez/Cloudflare-DDNS-Update?label=Release%20Date)
![GitHub release (latest by date)](https://img.shields.io/github/v/release/juanmcortez/Cloudflare-DDNS-Update)
[![GitHub issues](https://img.shields.io/github/issues/juanmcortez/Cloudflare-DDNS-Update?label=Feature%20Request%20-%20Issues)](https://github.com/juanmcortez/Cloudflare-DDNS-Update/issues)
[![GitHub license](https://img.shields.io/github/license/juanmcortez/Cloudflare-DDNS-Update?label=Project%20License)](https://github.com/juanmcortez/Cloudflare-DDNS-Update/blob/release/v1.1.0/LICENSE)

# Table of contents
- [Cloudflare DNS Service](#cloudflare-dns-service)
- [Fast install](#fast-install)
- [Step-by-Step install](#step-by-step-install)
- [Contributing](#contributing)
- [Support](#support)
- [Security](#security)
- [Contact](#contact)
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


# Contributing

See our [contribution page](https://github.com/juanmcortez/Cloudflare-DDNS-Update/blob/master/CONTRIBUTING.md) for details.


# Support

Your support is **greatly appreciated**!

If you feel that this project deserves it, or if it has been helpful to you ... or even if you are a merciful soul that want to expend some coins among developers ... feel free to:


<a href="https://www.buymeacoffee.com/juamcortez" target="_blank"><img src="https://cdn.buymeacoffee.com/buttons/lato-red.png" alt="Buy Me A Coffee" height="51" width="217" style="height: 51px !important;width: 217px !important; border-radius: 5px; margin: 10px 0;" ></a>


If you can't ... don't worry about it ... I'm not going to judge you ... **but I can't say the same about what my dog thinks of you** ...

!["Gorda" judging you](https://github.com/juanmcortez/Cloudflare-DDNS-Update/blob/master/CloudflareDDNS/docs/images/judging_dog.png)


# Security

See our [security policy](https://github.com/juanmcortez/Cloudflare-DDNS-Update/blob/master/SECURITY.md) for details


# Contact

If you feel the need to contact us, feel free to email me at [juanm.cortez@gmail.com](mailto:juanm.cortez@gmail.com) with your inquiry. Will try to get back to you as soon as possible!.

# License

Licensed under the GNU General Public License v3.0. See the [LICENSE](https://github.com/juanmcortez/Cloudflare-DDNS-Update/blob/master/LICENSE) file for details.
