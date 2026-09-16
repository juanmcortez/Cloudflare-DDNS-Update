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

    public function __construct(array $responses)
    {
        $this->responses = $responses;
        parent::__construct();
    }

    protected function getCURLData($method, $domain, $action, $postData = null)
    {
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
        $cloudflareSuccessResponse = [
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

        $ddns = new TestableDDNSUpdate([$cloudflareSuccessResponse]);

        // Should complete without throwing.
        $ddns->update();
        $this->addToAssertionCount(1);
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
}
