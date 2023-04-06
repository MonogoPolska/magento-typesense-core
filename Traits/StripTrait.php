<?php
declare(strict_types=1);

namespace Monogo\TypesenseCore\Traits;

use DOMDocument;

trait StripTrait
{
    /**
     * @param string $s
     * @param array $completeRemoveTags
     * @return string
     */
    public function strip(string $s, array $completeRemoveTags = []): string
    {
        if ($completeRemoveTags && $completeRemoveTags !== [] && $s) {
            $dom = new DOMDocument();
            libxml_use_internal_errors(true);
            $dom->loadHTML(mb_encode_numericentity($s, [0x80, 0x10FFFF, 0, ~0], 'UTF-8'));
            libxml_use_internal_errors(false);

            $toRemove = [];
            foreach ($completeRemoveTags as $tag) {
                $removeTags = $dom->getElementsByTagName($tag);

                foreach ($removeTags as $item) {
                    $toRemove[] = $item;
                }
            }

            foreach ($toRemove as $item) {
                $item->parentNode->removeChild($item);
            }

            $s = $dom->saveHTML();
        }

        $s = html_entity_decode($s, 0, 'UTF-8');

        $s = trim(preg_replace('/\s+/', ' ', $s));
        $s = preg_replace('/&nbsp;/', ' ', $s);
        $s = preg_replace('!\s+!', ' ', $s);
        $s = preg_replace('/\{\{[^}]+\}\}/', ' ', $s);
        $s = strip_tags($s);
        $s = trim($s);

        return $s;
    }
}
