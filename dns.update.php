<?php
/**
 * Cloudflare DDNS Update
 *
 * @category Application
 * @package  Cloudflare-DDNS-Update
 * @author   Juan M. Cortéz <juanm.cortez@gmail.com>
 * @license  GNU General Public License v3.0
 * @link     https://github.com/juanmcortez/Cloudflare-DDNS-Update
 */

require_once __DIR__.'/vendor/autoload.php';

$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Syslog
openlog("CloudflareDDNSUdpate", LOG_PID | LOG_PERROR, LOG_LOCAL0);

try {

    // Check if credentials are in the env file. A scoped API_TOKEN is the
    // preferred (Bearer) authentication method; the legacy GLOBAL_API_KEY +
    // EMAIL pair is still supported as a deprecated fallback.
    $dotenv->ifPresent('API_TOKEN')->notEmpty();
    $dotenv->ifPresent('GLOBAL_API_KEY')->notEmpty();
    $dotenv->ifPresent('EMAIL')->notEmpty();

    $hasApiToken = !empty($_ENV['API_TOKEN']);
    $hasLegacyAuth = !empty($_ENV['GLOBAL_API_KEY']) && !empty($_ENV['EMAIL']);

    if (!$hasApiToken && !$hasLegacyAuth) {
        throw new \Exception(
            'Missing credentials. Set API_TOKEN (recommended) or both '.
            'GLOBAL_API_KEY and EMAIL (deprecated) in your .env file.'
        );
    }

    // Check if the ip discovering system has been added to env file
    $dotenv->ifPresent('IP4_VAL')->notEmpty();
    $dotenv->ifPresent('IP6_VAL')->notEmpty();

    // Check if at least there is 1 domain to update.
    $dotenv->ifPresent('DOMAIN_1')->notEmpty();
    $dotenv->ifPresent('ZONEID_1')->notEmpty();

    // Start Class Processing
    $dnsUpdate = new \CloudflareDDNS\DDNSUpdate();
    $dnsUpdate->update();
    syslog(LOG_INFO, "Success!! Script processed.");

} catch (\Exception $exc) {

    // Show error
    echo "\nError! ".$exc->getMessage()." Fix the issue and restart.\n\n";
    syslog(LOG_ERR, "Error! ".$exc->getMessage()." Fix the issue and restart.");

}

// Syslog
closelog();
