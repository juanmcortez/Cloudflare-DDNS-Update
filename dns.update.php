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

try {

    // Check if credentials are in the env file.
    $dotenv->ifPresent('GLOBAL_API_KEY')->notEmpty();
    $dotenv->ifPresent('EMAIL')->notEmpty();

    // Check if the ip discovering system has been added to env file
    $dotenv->ifPresent('IP4_VAL')->notEmpty();
    $dotenv->ifPresent('IP6_VAL')->notEmpty();

    // Check if at least there is 1 domain to update.
    $dotenv->ifPresent('DOMAIN_1')->notEmpty();
    $dotenv->ifPresent('ZONEID_1')->notEmpty();

    // Start Class Processing
    $dnsUpdate = new \CloudflareDDNS\DDNSUpdate();
    $result = $dnsUpdate->update();

    // Show response of operation
    echo "\n$result\n";

} catch (\Exception $exc) {

    // Show error
    echo "\nError! ".$exc->getMessage()." Fix the issue and restart.\n\n";

}
