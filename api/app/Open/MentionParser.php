<?php

namespace App\Open;

use DOMDocument;
use DOMXPath;
use DOMElement;

class MentionParser
{
    private $content;
    private $data;
    private $computedValues;
    private $urlFriendly = false;

    public function __construct($content, $data, array $computedValues = [])
    {
        $this->content = $content;
        $this->data = $data;
        $this->computedValues = $computedValues;
    }

    /**
     * Set computed variable values
     */
    public function setComputedValues(array $computedValues): self
    {
        $this->computedValues = $computedValues;
        return $this;
    }

    public function urlFriendlyOutput(bool $enable = true): self
    {
        $this->urlFriendly = $enable;
        return $this;
    }

    public function parse()
    {
        $doc = new DOMDocument();
        // Disable libxml errors and use internal errors
        $internalErrors = libxml_use_internal_errors(true);

        // Wrap the content in a root element to ensure it's valid XML
        $wrappedContent = '<root>' . $this->content . '</root>';

        // Load HTML, using UTF-8 encoding
        $doc->loadHTML(mb_convert_encoding($wrappedContent, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        // Restore libxml error handling
        libxml_use_internal_errors($internalErrors);

        $xpath = new DOMXPath($doc);

        $mentionElements = $xpath->query("//span[@mention or @mention='true']");

        foreach ($mentionElements as $element) {
            if ($element instanceof DOMElement) {
                $fieldId = $element->getAttribute('mention-field-id');
                $fallback = $element->getAttribute('mention-fallback');
                $value = $this->getData($fieldId);

                if ($value !== null) {
                    $textNode = $doc->createTextNode(is_array($value) ? implode($this->urlFriendly ? ',+' : ', ', $value) : $value);
                    $element->parentNode->replaceChild($textNode, $element);
                } elseif ($fallback) {
                    $textNode = $doc->createTextNode($fallback);
                    $element->parentNode->replaceChild($textNode, $element);
                } else {
                    $element->parentNode->removeChild($element);
                }
            }
        }

        // Extract and return the processed HTML content
        $result = $doc->saveHTML($doc->getElementsByTagName('root')->item(0));

        // Remove the root tags we added
        $result = preg_replace('/<\/?root>/', '', $result);

        // Trim whitespace and convert HTML entities back to UTF-8 characters
        $result = trim(html_entity_decode($result, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return $result;
    }

    public function parseAsText()
    {
        // First use the existing parse method to handle mentions
        $html = $this->parse();

        $doc = new DOMDocument();
        $internalErrors = libxml_use_internal_errors(true);

        // Wrap in root element
        $wrappedContent = '<root>' . $html . '</root>';

        $doc->loadHTML(mb_convert_encoding($wrappedContent, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_use_internal_errors($internalErrors);

        // Convert HTML to plain text with proper line breaks
        $text = '';
        $this->domToText($doc->getElementsByTagName('root')->item(0), $text);

        // Clean up the text:
        // 1. Remove escaped newlines
        // 2. Replace multiple newlines with single newline
        // 3. Trim whitespace
        $text = str_replace(['\\n', '\n'], "\n", $text);
        $text = preg_replace('/\n+/', "\n", trim($text));

        // Ensure each line has exactly one email
        $lines = explode("\n", $text);
        $lines = array_map('trim', $lines);
        $lines = array_filter($lines); // Remove empty lines

        return implode("\n", $lines);
    }

    private function domToText($node, &$text)
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            $text .= $node->nodeValue;
            return;
        }

        $block_elements = ['div', 'p', 'br', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li'];
        $nodeName = strtolower($node->nodeName);

        // Add newline before block elements
        if (in_array($nodeName, $block_elements)) {
            $text .= "\n";
        }

        if ($node->hasChildNodes()) {
            foreach ($node->childNodes as $child) {
                $this->domToText($child, $text);
            }
        }

        // Add newline after block elements
        if (in_array($nodeName, $block_elements)) {
            $text .= "\n";
        }
    }

    private function getData($fieldId)
    {
        // First check regular field data
        $value = collect($this->data)->firstWhere('id', $fieldId)['value'] ?? null;

        // If not found, check computed variables (cv_ prefix)
        if ($value === null && isset($this->computedValues[$fieldId])) {
            $value = $this->computedValues[$fieldId];
        }

        if (is_object($value)) {
            $value = (array) $value;
        }

        if ($this->urlFriendly && $value !== null) {
            return is_array($value)
                ? array_map('urlencode', $value)
                : urlencode($value);
        }

        return $value;
    }
}
