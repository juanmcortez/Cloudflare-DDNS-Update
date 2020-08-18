<?php
/**
 * Cloudflare DDNS Update Class
 *
 * @category Application
 * @package  Cloudflare-DDNS-Update
 * @author   Juan M. Cortéz <juanm.cortez@gmail.com>
 * @license  GNU General Public License v3.0
 * @link     https://github.com/juanmcortez/Cloudflare-DDNS-Update
 */

namespace CloudflareDDNS;

class DDNSUpdate
{
    // Protected credentials
    protected $ddns_email;
    protected $ddns_gapik;

    // Private data
    private $_ddns_domain;

    private $_ddns_apiurl = "https://api.cloudflare.com/client/v4/zones/";

    private $_IPv4Service;
    private $_IPv6Service;

    private $_currentIPv4;
    private $_currentIPv6;

    private $_currDNSInfo;


    /**
     * Initiate the class
     */
    public function __construct()
    {
        // Load credentials
        $this->ddns_email = $_ENV['EMAIL'];
        $this->ddns_gapik = $_ENV['GLOBAL_API_KEY'];

        // Set services
        $this->_IPv4Service = $_ENV['IP4_VAL'];
        $this->_IPv6Service = $_ENV['IP6_VAL'];

        // Load domains
        foreach ($_ENV as $key => $value) {
            if (strpos($key, 'DOMAIN') !== false && $value != '') {
                $tmpKey = explode('_', $key);
                $newKey = intval($tmpKey[1]);
                $this->_ddns_domain[$newKey]['DOMAIN'] = $value;
            }
            if (strpos($key, 'ZONEID') !== false && $value != '') {
                $tmpKey = explode('_', $key);
                $newKey = intval($tmpKey[1]);
                $this->_ddns_domain[$newKey]['ZONEID'] = $value;
            }
        }
    }


    /**
     * Self explanatory
     *
     * @return void
     */
    private function _findCurrentIPofServer()
    {
        // Get ipv4
        $this->_currentIPv4 = file_get_contents($this->_IPv4Service);
        // Get ipv6
        $this->_currentIPv6 = file_get_contents($this->_IPv6Service);
    }


    /**
     * Self explanatory
     *
     * @return void
     */
    private function _domainsDNSDetails()
    {
        // Get details
        foreach ($this->_ddns_domain AS $key => $domain) {
            // Get info from curl
            $action = 'dns_records/?type=A&name='.$domain['DOMAIN'];
            $rspnse = $this->getCURLData('GET', $domain, $action);

            // Prepare for compare
            $this->_currDNSInfo[$key]['DOMAIN'] = $domain['DOMAIN'];
            $this->_currDNSInfo[$key]['ZONEID'] = $domain['ZONEID'];

            // Check status of response
            if (boolval($rspnse['success']) === true) {
                // Success
                foreach ($rspnse['result'] AS $idx => $domain) {
                    $this->_currDNSInfo[$key][$idx]['DNSID']    = $domain['id'];
                    $this->_currDNSInfo[$key][$idx]['TYPE']     = $domain['type'];
                    $this->_currDNSInfo[$key][$idx]['CONTENT']  = $domain['content'];
                    $this->_currDNSInfo[$key][$idx]['PROXIED']  = $domain['proxied'];
                    $this->_currDNSInfo[$key][$idx]['TTL']      = $domain['ttl'];
                }
            } else {
                // Fail!
                return $rspnse['errors'];
            }
        }
    }


    /**
     * Check if we need to update ip's
     *
     * @return void
     */
    private function _checkIPsStatus()
    {
        $rspnse = '';
        foreach ($this->_currDNSInfo AS $key => $current) {
            foreach ($current AS $idx => $item) {

                // Check IPv4
                if (is_array($item)
                    && $item['CONTENT'] != $this->_currentIPv4
                    && $item['TYPE'] == 'A'
                ) {
                    // Update IPv4
                    $method = 'PUT';
                    $action = 'dns_records/'.$item['DNSID'];

                    $upData['type']     = $item['TYPE'];
                    $upData['name']     = $current['DOMAIN'];
                    $upData['content']  = $this->_currentIPv4;
                    $upData['ttl']      = $item['TTL'];
                    $upData['proxied']  = (($item['PROXIED']) ? 'true' : 'false');

                    $rspnse[] = $this->getCURLData($method, $current, $action, $upData);
                }

                // Check IPv6
                if (is_array($item)
                    && $item['CONTENT'] != $this->_currentIPv6
                    && $item['TYPE'] == 'AAAA'
                ) {
                    // Update IPv6
                    $method = 'PUT';
                    $action = 'dns_records/'.$item['DNSID'];

                    $upData['type']     = $item['TYPE'];
                    $upData['name']     = $current['DOMAIN'];
                    $upData['content']  = $this->_currentIPv6;
                    $upData['ttl']      = $item['TTL'];
                    $upData['proxied']  = (($item['PROXIED']) ? 'true' : 'false');

                    $rspnse[] = $this->getCURLData($method, $current, $action, $upData);
                }

            }
        }
        return $rspnse;
    }


    /**
     * This is a function that can be re-used for all the curl requirements.
     *
     * @param string $method   HTTP method to implement in curl.
     * @param array  $domain   Domains to query.
     * @param string $action   Action to query.
     * @param array  $postData Data to update.
     *
     * @return array
     */
    protected function getCURLData( $method, $domain, $action, $postData=null)
    {
        $curlURL  = $this->_ddns_apiurl;
        $curlURL .= $domain['ZONEID']."/";
        $curlURL .= $action;

        $curlAUTH[] = 'Content-Type: application/json';
        $curlAUTH[] = 'X-Auth-Email: '.$this->ddns_email;
        $curlAUTH[] = 'X-Auth-Key: '.$this->ddns_gapik;

        // Start CURL actions
        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $curlURL);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_ENCODING, "utf-8");
        curl_setopt($curl, CURLOPT_MAXREDIRS, 10);
        curl_setopt($curl, CURLOPT_TIMEOUT, 0);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);

        // If we are updating info
        if ($method == 'PUT' && $postData != null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($postData));
        }
        // If we are updating info

        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $curlAUTH);

        // Process
        $curlRsp = curl_exec($curl);
        curl_close($curl);
        // End CURL actions

        // Return
        return json_decode($curlRsp, true);
    }


    /**
     * Script response format
     *
     * @param string $status Response status
     * @param array  $data   Response data
     *
     * @return void
     */
    private function _buildResponse($status, $data)
    {
        $response = '';
        if (is_array($data)) {
            foreach ($data AS $item) {
                if (boolval($item['success']) === true) {
                    $response .= $item['result']['name']." updated to ".$item['result']['content']."\n";
                } else {
                    foreach ($item['errors'] AS $error) {
                        $response .= $error."\n";
                    }
                }
            }
        } else {
            $response .= ucfirst($status)."!! No changes.\n";
        }
        return $response;
    }


    /**
     * Update dns records
     *
     * @return void
     */
    public function update()
    {
        // Syslog
        openlog("CloudflareDDNSUdpate", LOG_PID | LOG_PERROR, LOG_LOCAL0);

        // Get the current ip addresses
        $this->_findCurrentIPofServer();

        // Get DNS details on the domains
        $status = $this->_domainsDNSDetails();

        // Check if needs to update.
        if (!isset($status['errors'])) {

            // Everything went ok, check
            $update = $this->_checkIPsStatus();
            syslog(LOG_INFO, $this->_buildResponse('success', $update));

        } else {

            // Errors found!
            echo $this->_buildResponse('error', $status);
            syslog(LOG_ERR, $this->_buildResponse('error', $status));

        }

        // Syslog
        closelog();

    }
}
