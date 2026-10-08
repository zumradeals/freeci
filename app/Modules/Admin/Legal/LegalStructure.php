<?php

namespace App\Modules\Admin\Legal;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Lit la structure d'un texte déjà rendu (Markdown → HTML) pour la page « Comment ça marche » : un paragraphe d'introduction, une liste numérotée
 * (les étapes) et des sections « ## ». Le texte reste celui de l'administration ; rien n'est ajouté ni réécrit. Si la forme attendue n'est pas
 * trouvée, la méthode renvoie null et la page affiche le texte tel quel.
 */
final class LegalStructure
{
    /** @return array{lead: string, stepsTitle: string, steps: list<array{title: string, text: string}>, sections: list<array{title: string, html: string}>}|null */
    public static function parse(string $html): ?array
    {
        if (trim($html) === '') {
            return null;
        }
        $doc = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?><div id="root">'.$html.'</div>', LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $root = $doc->getElementById('root');
        if ($root === null) {
            return null;
        }

        $lead = '';
        $stepsTitle = '';
        $steps = [];
        $sections = [];
        $current = null;           // [title, html[]]
        $stepsFrom = null;
        foreach (iterator_to_array($root->childNodes) as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }
            if ($node->nodeName === 'h2') {
                if ($current !== null) {
                    $sections[] = $current;
                }
                $current = ['title' => trim($node->textContent), 'nodes' => []];

                continue;
            }
            if ($current === null) {
                if ($lead === '' && $node->nodeName === 'p') {
                    $lead = self::inner($node);

                    continue;
                }

                return null;                                     // du contenu inattendu avant la première section : on n'altère rien
            }
            $current['nodes'][] = $node;
        }
        if ($current !== null) {
            $sections[] = $current;
        }

        $cards = [];
        foreach ($sections as $s) {
            $first = $s['nodes'][0] ?? null;
            if ($stepsFrom === null && $first instanceof DOMElement && $first->nodeName === 'ol' && count($s['nodes']) === 1) {
                $stepsFrom = $s['title'];
                foreach ($first->getElementsByTagName('li') as $li) {
                    $strong = $li->getElementsByTagName('strong')->item(0);
                    $title = $strong ? rtrim(trim($strong->textContent), ' .:') : '';
                    $text = trim(self::inner($li));
                    if ($strong) {
                        $text = trim(preg_replace('/^\s*<strong>.*?<\/strong>\s*/su', '', $text) ?? $text);
                    }
                    $steps[] = ['title' => $title, 'text' => $text];
                }

                continue;
            }
            $cards[] = ['title' => $s['title'], 'html' => implode('', array_map(fn (DOMNode $n) => self::outer($n), $s['nodes']))];
        }
        if ($steps === [] || in_array('', array_column($steps, 'title'), true)) {
            return null;
        }

        return ['lead' => $lead, 'stepsTitle' => (string) $stepsFrom, 'steps' => $steps, 'sections' => $cards];
    }

    private static function inner(DOMNode $n): string
    {
        $out = '';
        foreach ($n->childNodes as $c) {
            $out .= $n->ownerDocument->saveHTML($c);
        }

        return $out;
    }

    private static function outer(DOMNode $n): string
    {
        return $n->ownerDocument->saveHTML($n);
    }
}
