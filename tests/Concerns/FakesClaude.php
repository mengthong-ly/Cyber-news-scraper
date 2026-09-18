<?php

namespace Tests\Concerns;

use Anthropic\Client;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;

/**
 * Replaces the Claude client with one that answers from a queue of canned HTTP responses.
 */
trait FakesClaude
{
    /** @var list<array{request: RequestInterface}> */
    protected array $claudeRequests = [];

    /**
     * @param  list<Response>  $responses
     */
    protected function fakeClaude(array $responses): void
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->claudeRequests));

        $this->app->instance(Client::class, new Client(
            apiKey: 'test-key',
            requestOptions: ['transporter' => new GuzzleClient(['handler' => $stack]), 'maxRetries' => 0],
        ));
    }

    /**
     * @param  array<string, mixed>|string  $output  JSON-encoded as the first text block
     */
    protected static function claudeMessage(array|string $output, string $stopReason = 'end_turn'): array
    {
        return [
            'id' => 'msg_test',
            'type' => 'message',
            'role' => 'assistant',
            'model' => 'claude-opus-5',
            'content' => [['type' => 'text', 'text' => is_string($output) ? $output : json_encode($output)]],
            'stop_reason' => $stopReason,
            'stop_sequence' => null,
            'stop_details' => null,
            'container' => null,
            'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
        ];
    }

    protected static function jsonResponse(array $body): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], json_encode($body));
    }

    protected static function batch(string $status): array
    {
        return [
            'id' => 'msgbatch_test',
            'type' => 'message_batch',
            'processing_status' => $status,
            'request_counts' => ['processing' => 0, 'succeeded' => 1, 'errored' => 0, 'canceled' => 0, 'expired' => 0],
            'created_at' => '2026-09-17T00:00:00Z',
            'expires_at' => '2026-09-18T00:00:00Z',
            'ended_at' => $status === 'ended' ? '2026-09-17T01:00:00Z' : null,
            'archived_at' => null,
            'cancel_initiated_at' => null,
            'results_url' => $status === 'ended' ? 'https://api.anthropic.com/v1/messages/batches/msgbatch_test/results' : null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    protected static function jsonlResponse(array $lines): Response
    {
        return new Response(200, ['Content-Type' => 'application/x-jsonl'], implode("\n", array_map('json_encode', $lines))."\n");
    }
}
