<?php
/**
 * Unit tests for CloudflareDDNS\DDNSUpdate covering Cloudflare API
 * success/failure response detection.
 */

namespace CloudflareDDNS\Tests;

use CloudflareDDNS\DDNSUpdate;
use PHPUnit\Framework\TestCase;

/**
 * Test double that replaces the real cURL call with canned, in-memory
 * Cloudflare API responses so the class can be exercised without any
 * network access.
 */
class TestableDDNSUpdate extends DDNSUpdate
{
    /** @var array<int, mixed> */
    private $responses;

    /** @var int */
    private $callIndex = 0;

    /**
     * Every getCURLData() call made during the test, in order, captured so
     * assertions can verify which record types/actions were queried or
     * updated (e.g. that AAAA lookups are skipped/performed as expected).
     *
     * @var array<int, array{method: string, domain: array, action: string, postData: ?array}>
     */
    public $calls = [];

    public function __construct(array $responses)
    {
        $this->responses = $responses;
        parent::__construct();
    }

    protected function getCURLData($method, $domain, $action, $postData = null)
    {
        $this->calls[] = [
            'method'   => $method,
            'domain'   => $domain,
            'action'   => $action,
            'postData' => $postData,
        ];

        $response = $this->responses[$this->callIndex] ?? null;
        $this->callIndex++;
        return $response;
    }
}

class DDNSUpdateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Minimal env required by the constructor / update() flow. The IP
        // lookup services point at the `data://` stream wrapper so no real
        // network access is required.
        $_ENV['API_TOKEN']    = 'unit-test-token';
        $_ENV['GLOBAL_API_KEY'] = '';
        $_ENV['EMAIL']        = '';
        $_ENV['IP4_VAL']      = 'data://text/plain,203.0.113.10';
        $_ENV['IP6_VAL']      = 'data://text/plain,2001:db8::1';
        $_ENV['DOMAIN_1']     = 'example.com';
        $_ENV['ZONEID_1']     = 'zone123';
    }

    protected function tearDown(): void
    {
        unset(
            $_ENV['API_TOKEN'],
            $_ENV['GLOBAL_API_KEY'],
            $_ENV['EMAIL'],
            $_ENV['IP4_VAL'],
            $_ENV['IP6_VAL'],
            $_ENV['DOMAIN_1'],
            $_ENV['ZONEID_1']
        );

        parent::tearDown();
    }

    /**
     * Reproduces the reported bug: a real Cloudflare API failure response
     * (`success: false` + an `errors` array of error objects) must be
     * detected as a failure and surfaced with a clear message, not
     * silently treated as a success.
     */
    public function testUpdateThrowsWhenCloudflareReturnsAFailureResponse(): void
    {
        $cloudflareFailureResponse = [
            'success'  => false,
            'errors'   => [
                ['code' => 6003, 'message' => 'Invalid request headers'],
            ],
            'messages' => [],
            'result'   => null,
        ];

        $ddns = new TestableDDNSUpdate([$cloudflareFailureResponse]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid request headers');

        $ddns->update();
    }

    /**
     * An empty/malformed response (e.g. curl error, HTML error page, JSON
     * decode failure) must also be treated as a failure rather than a
     * success, since it can never satisfy `success === true`.
     */
    public function testUpdateThrowsWhenCloudflareReturnsAMalformedResponse(): void
    {
        $ddns = new TestableDDNSUpdate([null]);

        $this->expectException(\Exception::class);

        $ddns->update();
    }

    /**
     * A genuine success response (`success: true`) must be processed
     * normally and must not throw.
     */
    public function testUpdateDoesNotThrowOnSuccessResponse(): void
    {
        $cloudflareARecordResponse = [
            'success'  => true,
            'errors'   => [],
            'messages' => [],
            'result'   => [
                [
                    'id'      => 'record123',
                    'type'    => 'A',
                    // Matches IP4_VAL above, so no update PUT is triggered.
                    'content' => '203.0.113.10',
                    'proxied' => false,
                    'ttl'     => 300,
                ],
            ],
        ];

        $cloudflareAAAARecordResponse = [
            'success'  => true,
            'errors'   => [],
            'messages' => [],
            'result'   => [
                [
                    'id'      => 'record456',
                    'type'    => 'AAAA',
                    // Matches IP6_VAL above, so no update PUT is triggered.
                    'content' => '2001:db8::1',
                    'proxied' => false,
                    'ttl'     => 300,
                ],
            ],
        ];

        // IPv6 is available in setUp(), so a second (AAAA) lookup is made.
        $ddns = new TestableDDNSUpdate([$cloudflareARecordResponse, $cloudflareAAAARecordResponse]);

        // Should complete without throwing.
        $ddns->update();
        $this->addToAssertionCount(1);

        $this->assertCount(2, $ddns->calls);
        $this->assertStringContainsString('type=A&', $ddns->calls[0]['action']);
        $this->assertStringContainsString('type=AAAA&', $ddns->calls[1]['action']);
    }

    /**
     * Directly exercises the private response-normalization helper with a
     * realistic Cloudflare error payload to ensure the human-readable
     * message is built from the `errors` array correctly.
     */
    public function testNormalizeApiErrorExtractsMessagesFromCloudflareShape(): void
    {
        $ddns = new TestableDDNSUpdate([]);

        $reflection = new \ReflectionMethod(DDNSUpdate::class, '_normalizeApiError');
        $reflection->setAccessible(true);

        $result = $reflection->invoke($ddns, [
            'success' => false,
            'errors'  => [
                ['code' => 1000, 'message' => 'Invalid API Token'],
                ['code' => 1003, 'message' => 'Invalid or missing zone id'],
            ],
            'messages' => [],
            'result'   => null,
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame(
            [
                'Invalid API Token (code 1000)',
                'Invalid or missing zone id (code 1003)',
            ],
            $result['errors']
        );
    }

    /**
     * A `null`/non-array response (e.g. a curl failure or non-JSON body)
     * must still normalize to a failure with a helpful fallback message,
     * instead of causing a fatal error or being read as a success.
     */
    public function testNormalizeApiErrorHandlesNullResponse(): void
    {
        $ddns = new TestableDDNSUpdate([]);

        $reflection = new \ReflectionMethod(DDNSUpdate::class, '_normalizeApiError');
        $reflection->setAccessible(true);

        $result = $reflection->invoke($ddns, null);

        $this->assertFalse($result['success']);
        $this->assertNotEmpty($result['errors']);
    }

    /**
     * When the host has a public IPv6 address, a stale AAAA record (whose
     * content differs from the detected IPv6) must be fetched and then
     * updated via a PUT request with the new IPv6 content - the same way
     * stale A records are handled for IPv4.
     */
    public function testUpdateFetchesAndUpdatesStaleAAAARecordWhenIPv6Available(): void
    {
        $aRecordResponse = [
            'success'  => true,
            'errors'   => [],
            'messages' => [],
            'result'   => [
                [
                    'id'      => 'recordA',
                    'type'    => 'A',
                    // Matches IP4_VAL from setUp(), so no A update is triggered.
                    'content' => '203.0.113.10',
                    'proxied' => false,
                    'ttl'     => 300,
                ],
            ],
        ];

        $aaaaRecordResponse = [
            'success'  => true,
            'errors'   => [],
            'messages' => [],
            'result'   => [
                [
                    'id'      => 'recordAAAA',
                    'type'    => 'AAAA',
                    // Stale - does not match IP6_VAL (2001:db8::1) from setUp().
                    'content' => '2001:db8::dead',
                    'proxied' => false,
                    'ttl'     => 300,
                ],
            ],
        ];

        $updatePutResponse = [
            'success'  => true,
            'errors'   => [],
            'messages' => [],
            'result'   => [
                'name'    => 'example.com',
                'content' => '2001:db8::1',
            ],
        ];

        $ddns = new TestableDDNSUpdate([$aRecordResponse, $aaaaRecordResponse, $updatePutResponse]);

        $ddns->update();

        // 2 GET lookups (A + AAAA) followed by 1 PUT update for the stale AAAA record.
        $this->assertCount(3, $ddns->calls);

        $this->assertSame('GET', $ddns->calls[0]['method']);
        $this->assertStringContainsString('type=A&name=example.com', $ddns->calls[0]['action']);

        $this->assertSame('GET', $ddns->calls[1]['method']);
        $this->assertStringContainsString('type=AAAA&name=example.com', $ddns->calls[1]['action']);

        $this->assertSame('PUT', $ddns->calls[2]['method']);
        $this->assertSame('dns_records/recordAAAA', $ddns->calls[2]['action']);
        $this->assertSame('AAAA', $ddns->calls[2]['postData']['type']);
        $this->assertSame('2001:db8::1', $ddns->calls[2]['postData']['content']);
    }

    /**
     * When the IPv6 lookup service is unavailable/empty (e.g. the host has
     * no IPv6 connectivity), the tool must not attempt an AAAA lookup at
     * all, and must keep behaving exactly like an IPv4-only setup.
     */
    public function testUpdateSkipsAAAALookupWhenIPv6Unavailable(): void
    {
        // Simulate a failed/empty IPv6 lookup (e.g. no IPv6 connectivity).
        $_ENV['IP6_VAL'] = 'data://text/plain,';

        $aRecordResponse = [
            'success'  => true,
            'errors'   => [],
            'messages' => [],
            'result'   => [
                [
                    'id'      => 'recordA',
                    'type'    => 'A',
                    'content' => '203.0.113.10',
                    'proxied' => false,
                    'ttl'     => 300,
                ],
            ],
        ];

        $ddns = new TestableDDNSUpdate([$aRecordResponse]);

        $ddns->update();

        // Only the A lookup should have been made - no AAAA lookup/update.
        $this->assertCount(1, $ddns->calls);
        $this->assertStringContainsString('type=A&name=example.com', $ddns->calls[0]['action']);
    }

    /**
     * A malformed/non-IP value returned by the IPv6 lookup service (e.g. an
     * HTML error page) must be treated as "no IPv6 available", not as a
     * literal record content to compare/update against.
     */
    public function testUpdateSkipsAAAALookupWhenIPv6ServiceReturnsInvalidValue(): void
    {
        $_ENV['IP6_VAL'] = 'data://text/plain,<html>Service Unavailable</html>';

        $aRecordResponse = [
            'success'  => true,
            'errors'   => [],
            'messages' => [],
            'result'   => [
                [
                    'id'      => 'recordA',
                    'type'    => 'A',
                    'content' => '203.0.113.10',
                    'proxied' => false,
                    'ttl'     => 300,
                ],
            ],
        ];

        $ddns = new TestableDDNSUpdate([$aRecordResponse]);

        $ddns->update();

        $this->assertCount(1, $ddns->calls);
        $this->assertStringContainsString('type=A&name=example.com', $ddns->calls[0]['action']);
    }

    /**
     * A fresh AAAA record (content already matches the detected IPv6) must
     * not trigger an update PUT request.
     */
    public function testUpdateDoesNotUpdateAAAARecordWhenAlreadyCurrent(): void
    {
        $aRecordResponse = [
            'success'  => true,
            'errors'   => [],
            'messages' => [],
            'result'   => [
                [
                    'id'      => 'recordA',
                    'type'    => 'A',
                    'content' => '203.0.113.10',
                    'proxied' => false,
                    'ttl'     => 300,
                ],
            ],
        ];

        $aaaaRecordResponse = [
            'success'  => true,
            'errors'   => [],
            'messages' => [],
            'result'   => [
                [
                    'id'      => 'recordAAAA',
                    'type'    => 'AAAA',
                    // Already matches IP6_VAL (2001:db8::1) from setUp().
                    'content' => '2001:db8::1',
                    'proxied' => false,
                    'ttl'     => 300,
                ],
            ],
        ];

        $ddns = new TestableDDNSUpdate([$aRecordResponse, $aaaaRecordResponse]);

        $ddns->update();

        // Only the 2 GET lookups - no PUT update for either record.
        $this->assertCount(2, $ddns->calls);
        $this->assertSame('GET', $ddns->calls[0]['method']);
        $this->assertSame('GET', $ddns->calls[1]['method']);
    }
}
