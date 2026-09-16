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
    protected $ddns_apitoken;
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
        // Load credentials - API_TOKEN (scoped, recommended) takes precedence
        // over the legacy Global API Key + Email pair.
        $this->ddns_apitoken = (!empty($_ENV['API_TOKEN'])) ? $_ENV['API_TOKEN'] : null;
        $this->ddns_email = (!empty($_ENV['EMAIL'])) ? $_ENV['EMAIL'] : null;
        $this->ddns_gapik = (!empty($_ENV['GLOBAL_API_KEY'])) ? $_ENV['GLOBAL_API_KEY'] : null;

        if (empty($this->ddns_apitoken) && (!empty($this->ddns_email) || !empty($this->ddns_gapik))) {
            openlog("CloudflareDDNSUdpate", LOG_PID | LOG_PERROR, LOG_LOCAL0);
            syslog(
                LOG_WARNING,
                "Deprecation warning: using GLOBAL_API_KEY/EMAIL auth is deprecated. ".
                "Please switch to a scoped API_TOKEN. See README.md for details."
            );
            closelog();
        }

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
        // Get ipv4 (required)
        $ipv4 = @file_get_contents($this->_IPv4Service);
        $this->_currentIPv4 = $this->_validateIP($ipv4, FILTER_FLAG_IPV4);

        // Get ipv6 (optional). Not every host has IPv6 connectivity, and the
        // configured lookup service may fail/be unreachable in that case, so
        // any failure here is treated as "no IPv6 available" rather than a
        // fatal error. This keeps IPv4-only setups working exactly as before.
        $ipv6 = (!empty($this->_IPv6Service)) ? @file_get_contents($this->_IPv6Service) : false;
        $this->_currentIPv6 = $this->_validateIP($ipv6, FILTER_FLAG_IPV6);
    }

    /**
     * Validate that a value is a well-formed IP address of the expected
     * family, trimming any surrounding whitespace/newlines that IP lookup
     * services commonly return.
     *
     * @param mixed $value Raw value returned by the IP lookup service.
     * @param int   $flag  FILTER_FLAG_IPV4 or FILTER_FLAG_IPV6.
     *
     * @return string|null The validated/trimmed IP, or null when invalid.
     */
    private function _validateIP($value, $flag)
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return (filter_var($value, FILTER_VALIDATE_IP, $flag) !== false) ? $value : null;
    }


    /**
     * Self explanatory
     *
     * @return void
     */
    private function _domainsDNSDetails()
    {
        // Always look up A/IPv4 records. Only look up AAAA/IPv6 records when
        // we successfully detected a public IPv6 address for this host, so
        // hosts without IPv6 connectivity keep behaving exactly as before
        // (a single A-record lookup/update per domain).
        $recordTypes = ['A'];
        if (!empty($this->_currentIPv6)) {
            $recordTypes[] = 'AAAA';
        }

        // Get details
        foreach ($this->_ddns_domain AS $key => $domain) {
            // Prepare for compare
            $this->_currDNSInfo[$key]['DOMAIN'] = $domain['DOMAIN'];
            $this->_currDNSInfo[$key]['ZONEID'] = $domain['ZONEID'];

            foreach ($recordTypes AS $type) {
                // Get info from curl
                $action = 'dns_records/?type='.$type.'&name='.$domain['DOMAIN'];
                $rspnse = $this->getCURLData('GET', $domain, $action);

                // Check status of response. Cloudflare's API always returns
                // a top-level "success" boolean; only trust the result when
                // it is explicitly true, since a malformed/empty response
                // must never be treated as a success.
                if (is_array($rspnse)
                    && array_key_exists('success', $rspnse)
                    && boolval($rspnse['success']) === true
                    && is_array($rspnse['result'] ?? null)
                ) {
                    // Success - append every returned record (there may be
                    // more than one A/AAAA record for the same name).
                    foreach ($rspnse['result'] AS $record) {
                        $this->_currDNSInfo[$key][] = [
                            'DNSID'   => $record['id'],
                            'TYPE'    => $record['type'],
                            'CONTENT' => $record['content'],
                            'PROXIED' => $record['proxied'],
                            'TTL'     => $record['ttl'],
                        ];
                    }
                } else {
                    // Fail! Normalize the response into a consistent
                    // ['success' => false, 'errors' => [...]] shape, whether
                    // Cloudflare returned a proper error payload or the
                    // response was empty/malformed (e.g. curl failure, HTML
                    // error page).
                    return $this->_normalizeApiError($rspnse);
                }
            }
        }

        return null;
    }

    /**
     * Normalize a (possibly malformed) Cloudflare API response into a
     * consistent failure structure with human-readable error messages.
     *
     * Cloudflare's documented error shape is:
     *   {"success": false, "errors": [{"code": 1000, "message": "..."}], ...}
     *
     * @param mixed $rspnse Decoded JSON response (or null/false on failure).
     *
     * @return array{success: bool, errors: string[]}
     */
    private function _normalizeApiError($rspnse)
    {
        $errors = [];

        if (is_array($rspnse) && !empty($rspnse['errors']) && is_array($rspnse['errors'])) {
            foreach ($rspnse['errors'] as $error) {
                if (is_array($error) && isset($error['message'])) {
                    $code = isset($error['code']) ? ' (code '.$error['code'].')' : '';
                    $errors[] = $error['message'].$code;
                } elseif (is_string($error) && $error !== '') {
                    $errors[] = $error;
                }
            }
        }

        if (empty($errors)) {
            $errors[] = 'Cloudflare API request failed with an empty or '
                .'unrecognized response.';
        }

        return [
            'success' => false,
            'errors'  => $errors,
        ];
    }


    /**
     * Check if we need to update ip's
     *
     * @return void
     */
    private function _checkIPsStatus()
    {
        // Must be an array (not a string) since successful updates append
        // to it below via `$rspnse[] = ...`; `_buildResponse()` already
        // handles an empty array the same way it handled an empty string
        // (both are falsy, so "No changes" is reported when nothing updates).
        $rspnse = [];
        foreach ($this->_currDNSInfo AS $key => $current) {
            foreach ($current AS $idx => $item) {

                if (!is_array($item)) {
                    continue;
                }

                // Check IPv4. The `!empty($this->_currentIPv4)` guard avoids
                // ever pushing an empty/invalid value as the new record
                // content if the IPv4 lookup service failed.
                if ($item['TYPE'] == 'A'
                    && !empty($this->_currentIPv4)
                    && $item['CONTENT'] != $this->_currentIPv4
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

                // Check IPv6. AAAA records are only ever present in
                // `_currDNSInfo` when a public IPv6 address was detected
                // (see `_domainsDNSDetails()`), but the `!empty()` guard is
                // kept here too as defense in depth so an AAAA record is
                // never overwritten with an empty value.
                if ($item['TYPE'] == 'AAAA'
                    && !empty($this->_currentIPv6)
                    && $item['CONTENT'] != $this->_currentIPv6
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
        if (!empty($this->ddns_apitoken)) {
            // Preferred: scoped API Token via Bearer authentication.
            $curlAUTH[] = 'Authorization: Bearer '.$this->ddns_apitoken;
        } else {
            // Deprecated fallback: legacy Global API Key + Email auth.
            $curlAUTH[] = 'X-Auth-Email: '.$this->ddns_email;
            $curlAUTH[] = 'X-Auth-Key: '.$this->ddns_gapik;
        }

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
        if (is_array($data) && !empty($data)) {
            foreach ($data AS $item) {
                if (is_array($item) && array_key_exists('success', $item)) {
                    // Raw Cloudflare API response (e.g. from a PUT update).
                    if (boolval($item['success']) === true) {
                        $response .= $item['result']['name']." updated to ".$item['result']['content']."\n";
                    } else {
                        $normalized = $this->_normalizeApiError($item);
                        foreach ($normalized['errors'] AS $error) {
                            $response .= $error."\n";
                        }
                    }
                } elseif (is_string($item)) {
                    // Already-normalized error message string.
                    $response .= $item."\n";
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

        // Check if the Cloudflare API call failed. `_domainsDNSDetails()`
        // returns null on success, or a normalized ['success' => false,
        // 'errors' => [...]] array when the API reported a failure.
        $failed = is_array($status)
            && array_key_exists('success', $status)
            && $status['success'] === false;

        if (!$failed) {

            // Everything went ok, check
            $update = $this->_checkIPsStatus();
            syslog(LOG_INFO, $this->_buildResponse('success', $update));

            // Syslog
            closelog();

        } else {

            // Errors found!
            $message = $this->_buildResponse('error', $status['errors']);
            echo $message;
            syslog(LOG_ERR, $message);

            // Syslog
            closelog();

            // Surface the failure to the caller so it isn't silently
            // swallowed as a success (dns.update.php exits non-zero on
            // this exception).
            throw new \Exception(trim($message) !== '' ? trim($message) : 'Cloudflare API request failed.');

        }

    }
}
