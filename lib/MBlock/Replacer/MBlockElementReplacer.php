<?php
/**
 * @author mail[at]joachim-doerr[dot]com Joachim Doerr
 * @package redaxo5
 * @license MIT
 */

namespace MBlock\Replacer;


use DOMDocument;
use DOMElement;
use MBlock\DOM\MBlockDOMTrait;
use MBlock\DTO\MBlockItem;

//use MBlock\DTO\MBlockItem;

class MBlockElementReplacer
{
    use MBlockDOMTrait;

    const PATTERN = '/(\[\w+\])(\[\w+\])/';
    const PATTERN_NESTED = '/(\[\w+\])(\[\w+\])(\[\w+\])(\[\w\])/';

    const PATTERN_ID = '/(_\d+){2}/';
    const PATTERN_ID_NESTED = '/(_\d+){2}(_\w+)(_\d+)/';

    /**
     * @param DOMElement $element
     * @param MBlockItem $item
     * @param null $nestedCount
     * @author Joachim Doerr
     */
    protected static function replaceName(DOMElement $element, MBlockItem $item, $nestedCount = null)
    {
        preg_match(self::PATTERN_NESTED, $element->getAttribute('name'), $nestedMatch);
        preg_match(self::PATTERN, $element->getAttribute('name'), $defaultMatch);

        if (is_int($nestedCount) && !empty($nestedMatch)) {
            $element->setAttribute('name', str_replace($nestedMatch[0], sprintf('%s[%d]%s[%d]', $nestedMatch[1], $nestedCount, $nestedMatch[3], $item->getItemId()), $element->getAttribute('name')));
            $name = explode('[', $element->getAttribute('name'));
            $element->setAttribute('data-name-value', $name[0]);
            $element->setAttribute('data-value-id', str_replace(array('[', ']'), '', $nestedMatch[1]));
            $element->setAttribute('data-parent-item-count', $nestedCount);
            $element->setAttribute('data-group-value', str_replace(array('[', ']'), '', $nestedMatch[3]));
            $element->setAttribute('data-item-count', str_replace(array('[', ']'), '', $item->getItemId()));
            $element->setAttribute('data-item-value', str_replace(']', '', array_pop($name)));
        } else if (!empty($defaultMatch)) {
            $element->setAttribute('name', str_replace($defaultMatch[0], sprintf('%s[%d]', $defaultMatch[1], $item->getItemId()), $element->getAttribute('name')));
            $name = explode('[', $element->getAttribute('name'));
            $element->setAttribute('data-name-value', $name[0]);
            $element->setAttribute('data-value-id', str_replace(array('[', ']'), '', $defaultMatch[1]));
            $element->setAttribute('data-item-count', $item->getItemId());
            $element->setAttribute('data-item-value', str_replace(']', '', array_pop($name)));
        }
    }

    /**
     * @param DOMElement $element
     * @param MBlockItem $item
     * @param bool $valueEmpty
     * @author Joachim Doerr
     */
    protected static function replaceValue(DOMElement $element, MBlockItem $item, $valueEmpty = false)
    {
        // get value key by name
        $matches = self::getName($element);
        // found
        if ($matches) {
            // node name switch
            switch ($element->nodeName) {
                default:
                case 'input':
                    if ($matches && is_array($item->getVal()) && array_key_exists($matches[1], $item->getVal())) {
                        $element->setAttribute('value', $item->getVal()[$matches[1]]);
                    }
                    // set default value or empty it
                    if ($valueEmpty) {
                        $element->setAttribute('value', ($element->hasAttribute('data-default-value')) ? $element->getAttribute('data-default-value') : '');
                    }
                    break;
                case 'textarea':
                    if ($matches && is_array($item->getVal()) && array_key_exists($matches[1], $item->getVal())) {
                        $result = $item->getVal();
                        $id = uniqid(md5(rand(1000, 9999)), true);
                        // node value cannot contains &
                        // so set a unique id there we replace later with the right value
                        $element->nodeValue = $id;

                        $valueResult = $result[$matches[1]];

                        // add the id to the result value
                        $result[$matches[1]] = array('id' => $id, 'value' => $valueResult);

                        // reset result
                        $item->setResult($result);
                    }
                    if ($valueEmpty) {
                        $element->nodeValue = ($element->hasAttribute('data-default-value')) ? $element->getAttribute('data-default-value') : '';
                    }
                    break;
            }
        }
    }

    /**
     * @param DOMElement $element
     * @param MBlockItem $item
     * @author Joachim Doerr
     */
    protected static function replaceSelectedData(DOMElement $element, MBlockItem $item)
    {
        // get value key by name
        $matches = self::getName($element);
        // found
        if ($matches) {
            // node name switch
            switch ($element->nodeName) {
                default:
                case 'select':
                    // if ($matches && array_key_exists($matches[1], $item->getVal())) $element->setAttribute('data-selected', $item->getVal()[$matches[1]]);
                    if ($matches && is_array($item->getVal()) && array_key_exists($matches[1], $item->getVal())) {
                        $element->setAttribute('data-selected', (!$element->hasAttribute('multiple')) ? $item->getVal()[$matches[1]] : rex_escape(json_encode($item->getVal()[$matches[1]]), 'html_attr'));
                    }
                    break;
            }
        }
    }

    /**
     * @param DOMElement $element
     * @param MBlockItem $item
     * @author Joachim Doerr
     */
    protected static function replaceChecked(DOMElement $element, MBlockItem $item)
    {
        // get value key by name
        $matches = self::getName($element);
        // found
        if ($matches) {
            // unset select
            if ($element->getAttribute('checked')) {
                $element->removeAttribute('checked');
            }
            // set select by value = result
            if ($matches && is_array($item->getVal()) && array_key_exists($matches[1], $item->getVal()) && $item->getVal()[$matches[1]] == $element->getAttribute('value')) {
                $element->setAttribute('checked', 'checked');
            }
        }
    }

    /**
     * @param DOMElement $select
     * @param DOMElement $option
     * @param MBlockItem $item
     * @author Joachim Doerr
     */
    protected static function replaceOptionSelect(DOMElement $select, DOMElement $option, MBlockItem $item)
    {
        // get value key by name
        $matches = self::getName($select);
        // found
        if ($matches) {
            // unset select
            if ($option->hasAttribute('selected')) {
                $option->removeAttribute('selected');
            }

            // set select by value = result
            if ($matches && is_array($item->getVal()) && array_key_exists($matches[1], $item->getVal())) {

                if (is_array($item->getVal()[$matches[1]])) {
                    $values = $item->getVal()[$matches[1]];
                } else {
                    $values = explode(',',$item->getVal()[$matches[1]]);
                }

                foreach ($values as $value) {
                    if ($value == $option->getAttribute('value')) {
                        $option->setAttribute('selected', 'selected');
                    }
                }
            }
        }
    }

    /**
     * @param DOMDocument $dom
     * @param DOMElement $element
     * @param MBlockItem $item
     * @return bool
     * @author Joachim Doerr
     */
    public static function replaceForId(MBlockItem $item)
    {
        /*
        // get input id
        if (!$elementId = $element->getAttribute('id')) return true;

        // ignore system elements
        if (strpos($elementId, 'REX_MEDIA') !== false
            or strpos($elementId, 'REX_LINK') !== false) {
            return false;
        }

        $id = preg_replace('/(_\d+){2}/i', '_' . $item->getItemId(), str_replace('-','_', $elementId));

        $dom = $item->getForm();

        $element->setAttribute('id', $id);
        // find label with for
        $matches = $dom->getElementsByTagName('label');

        if ($matches) {
            /** @var DOMElement $match *//*
            foreach ($matches as $match) {
                $for = $match->getAttribute('for');
                if ($for == $elementId) {
                    $match->setAttribute('for', $id);
                }
            }
        }
        return true;
        */
    }

    /**
     * @param DOMElement $element
     * @return mixed
     * @author Joachim Doerr
     */
    public static function getName(DOMElement $element)
    {
        preg_match('/^.*?\[(\w+)\]$/i', str_replace('[]','',$element->getAttribute('name')), $matches);
        return $matches;
    }
}