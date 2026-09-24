<?php

namespace App\Domain\Topics;

use App\Models\TopicSource;
use Carbon\Carbon;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Symfony\Component\CssSelector\CssSelectorConverter;

final class HtmlTopicSourceConnector extends AbstractTopicSourceConnector
{
    public function collect(TopicSource $source): array
    {
        $config = $source->parser_config ?? [];
        foreach (['item_selector', 'title_selector'] as $required) {
            if (! is_string($config[$required] ?? null) || ! $this->isAllowedSelector($config[$required])) {
                throw new MalformedTopicSourceException("HTML topic source [{$source->name}] is missing an allowed {$required}.");
            }
        }

        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$this->fetch($source)->body(), LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($document);
        $converter = new CssSelectorConverter;
        $nodes = $xpath->query($converter->toXPath($config['item_selector']));

        if ($nodes === false) {
            throw new MalformedTopicSourceException("HTML topic source [{$source->name}] could not be parsed.");
        }

        $items = [];
        foreach ($nodes as $index => $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $titleNode = $this->first($xpath, $converter, $node, $config['title_selector']);
            $title = trim((string) ($titleNode?->textContent ?? ''));
            if ($title === '') {
                continue;
            }

            $urlNode = isset($config['url_selector']) && $this->isAllowedSelector($config['url_selector'])
                ? $this->first($xpath, $converter, $node, $config['url_selector'])
                : ($node->tagName === 'a' ? $node : null);
            $url = $urlNode instanceof DOMElement ? trim((string) $urlNode->getAttribute('href')) : null;
            $sourceKey = $url ?: $title.'#'.$index;
            $summaryNode = isset($config['summary_selector']) && $this->isAllowedSelector($config['summary_selector'])
                ? $this->first($xpath, $converter, $node, $config['summary_selector'])
                : null;
            $dateNode = isset($config['published_at_selector']) && $this->isAllowedSelector($config['published_at_selector'])
                ? $this->first($xpath, $converter, $node, $config['published_at_selector'])
                : null;
            $date = trim((string) ($dateNode?->textContent ?? ''));

            $items[] = [
                'source_key' => $sourceKey,
                'title' => $title,
                'summary' => trim((string) ($summaryNode?->textContent ?? '')) ?: null,
                'url' => $url,
                'published_at' => $this->date($date),
                'raw_payload' => ['item_index' => $index],
            ];
        }

        return $items;
    }

    private function first(DOMXPath $xpath, CssSelectorConverter $converter, DOMElement $node, string $selector): ?DOMElement
    {
        if (! $this->isAllowedSelector($selector)) {
            return null;
        }

        $matches = $xpath->query($converter->toXPath($selector, 'descendant-or-self::'), $node);

        return $matches !== false && $matches->length > 0 && $matches->item(0) instanceof DOMElement
            ? $matches->item(0)
            : null;
    }

    private function isAllowedSelector(mixed $selector): bool
    {
        return is_string($selector)
            && preg_match('/^[a-z][a-z0-9]*(?:[.#][a-z][a-z0-9_-]*)?(?:\s+[a-z][a-z0-9]*(?:[.#][a-z][a-z0-9_-]*)?)*$/i', trim($selector)) === 1;
    }

    private function date(string $value): ?Carbon
    {
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
