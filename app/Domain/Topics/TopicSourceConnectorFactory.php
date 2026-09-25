<?php

namespace App\Domain\Topics;

use App\Models\TopicSource;

final class TopicSourceConnectorFactory
{
    public function __construct(private readonly ?RssTopicSourceConnector $rss = null) {}

    public function make(TopicSource $source): TopicSourceConnector
    {
        return match (strtolower((string) $source->type)) {
            'rss', 'atom' => $this->rss ?? new RssTopicSourceConnector,
            'json', 'json_api', 'api' => new JsonApiTopicSourceConnector,
            'html' => new HtmlTopicSourceConnector,
            default => throw new MalformedTopicSourceException("Unsupported topic source type [{$source->type}]."),
        };
    }
}
