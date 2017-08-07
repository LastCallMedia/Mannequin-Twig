<?php

/*
 * This file is part of Mannequin.
 *
 * (c) 2017 Last Call Media, Rob Bayliss <rob@lastcallmedia.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace LastCall\Mannequin\Twig;

use LastCall\Mannequin\Core\Exception\TemplateParsingException;

class TwigInspector implements TwigInspectorInterface
{
    private $twig;

    public function __construct(\Twig_Environment $twig)
    {
        $this->twig = $twig;
    }

    public function inspectLinked(\Twig_Source $source): array
    {
        try {
            $parsed = $this->twig->parse($this->twig->tokenize($source));
            $includes = self::walkNodes($parsed, \Twig_Node_Include::class);
            $embeds = self::walkEmbeds($parsed);
            $parents = self::getParents($parsed);

            return array_merge($includes, $embeds, $parents);
        } catch (\Twig_Error $e) {
            $message = sprintf('Twig error thrown during inspection of %s: %s',
                $source->getName(),
                $e->getMessage()
            );
            throw new TemplateParsingException($message, $e->getCode(), $e);
        }
    }

    private static function walkNodes(\Twig_Node $node, $forClass)
    {
        $includes = [];
        foreach ($node as $child) {
            if ($child instanceof \Twig_Node) {
                $includes = array_merge(
                    $includes,
                    self::walkNodes($child, $forClass)
                );
            }
        }
        if ($node instanceof $forClass) {
            $expr = $node->getNode('expr');

            if ($expr instanceof \Twig_Node_Expression_Constant) {
                $value = $expr->getAttribute('value');
                if ($value !== 'not_used') {
                    $includes[] = $value;
                }
            }
        }

        return $includes;
    }

    private static function walkEmbeds(\Twig_Node $node)
    {
        $includes = [];

        if ($node->hasAttribute(
                'embedded_templates'
            ) && !empty($node->getAttribute('embedded_templates'))
        ) {
            foreach ($node->getAttribute('embedded_templates') as $embedNode) {
                $includes = array_merge(
                    $includes,
                    self::getParents($embedNode)
                );
            }
        }

        return $includes;
    }

    private static function getParents(\Twig_Node $node)
    {
        $includes = [];
        if ($node->hasNode('parent')) {
            $parentNode = $node->getNode('parent');
            if ($parentNode instanceof \Twig_Node_Expression_Constant) {
                $includes[] = $parentNode->getAttribute('value');
            }
        }

        return $includes;
    }

    /**
     * {@inheritdoc}
     */
    public function inspectPatternData(\Twig_Source $source)
    {
        try {
            $template = $this->twig->load($source->getName());
            if ($template->hasBlock('patterninfo')) {
                return $template->renderBlock('patterninfo');
            }
        } catch (\Twig_Error $e) {
            $message = sprintf('Twig error thrown during inspection of %s: %s',
                $source->getName(),
                $e->getMessage()
            );
            throw new TemplateParsingException($message, $e->getCode(), $e);
        }

        return false;
    }
}
