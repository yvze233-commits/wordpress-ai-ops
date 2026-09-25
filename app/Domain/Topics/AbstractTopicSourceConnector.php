<?php

namespace App\Domain\Topics;

use App\Models\TopicSource;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

abstract class AbstractTopicSourceConnector implements TopicSourceConnector
{
    protected function fetch(TopicSource $source): Response
    {
        try {
            $response = $this->request()->get($source->url);
        } catch (ConnectionException $exception) {
            throw new TopicSourceException(
                "Unable to connect to topic source [{$source->name}].",
                true,
                $exception,
            );
        }

        if ($response->failed()) {
            $response->throw();
        }

        $maxBytes = (int) config('content-ops.topic_source_max_response_bytes', 4 * 1024 * 1024);
        if (strlen($response->body()) > $maxBytes) {
            throw new MalformedTopicSourceException("Topic source [{$source->name}] exceeded the response size limit.");
        }

        return $response;
    }

    protected function request(): PendingRequest
    {
        return Http::timeout((int) config('content-ops.topic_source_timeout', 30))
            ->connectTimeout((int) config('content-ops.topic_source_connect_timeout', 10))
            ->withHeaders([
                'User-Agent' => 'WordPress-AI-Ops/1.0 (+https://localhost; content topic reader)',
                'Accept' => 'application/rss+xml, application/atom+xml, application/json, text/html;q=0.9, */*;q=0.8',
            ]);
    }

    protected function scalar(mixed $value): ?string
    {
        if ($value === null || is_array($value) || is_object($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
