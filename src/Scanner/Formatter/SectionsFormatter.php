<?php

namespace Scanner\Formatter;

use Scanner\Page;

/**
 * Class SectionsFormatter
 * @package Scanner\Formatter
 */
class SectionsFormatter extends AbstractFormatter
{
    /**
     * Min amount of pages a section needs to have to get them grouped
     */
    const MIN_SECTIONS = 3;

    /**
     * @var array
     */
    protected $tree;

    /**
     * @var array
     */
    protected $orphans;

    /**
     * @var array
     */
    protected $notSimilarOrphans;

    /**
     * Passed as a callback to curl helper to be executed when handle finishes downloading content of the url
     *
     * @param Page $page
     */
    public function successCallback(Page $page)
    {
        $path = $this->getScannerHelper()->getUrlHelper()->getPath($page->getUrl()) ?: '/';
        $urls = $this->getScannerHelper()->getValidLinks($page->getContent(), $path);
        $this->addUrls($urls);
    }

    /**
     * @return array
     */
    public function getResult()
    {
        $sections = $this->getSections();
        foreach ($sections as &$section) {
            $section['url'] = $this->getScannerHelper()->getHostWithSchema() . $section['url'];
            foreach ($section['sub'] as &$sub) {
                $sub['url'] = $this->getScannerHelper()->getHostWithSchema() . $sub['url'];
            }
        }
        return $sections;
    }

    /**
     * Prepares one level tree for the list of urls (maps urls by section)
     */
    protected function prepareTree()
    {
        $this->tree = $this->orphans = $this->notSimilarOrphans = [];
        $minSubSections = 5;

        foreach ($this->urls as $url => $dummy) {
            $parts = explode('/', $url);
            // only consider those starting with focus (if set)
            if (isset($this->focus[1]) && isset($parts[1]) && $this->focus[1] != $parts[1]) {
                continue;
            }

            // orphans are extracted and looked at only for the 0 depth
            if (empty($parts[1])) {
                $this->orphans[] = $url;
                continue;
            }

            // first level tree
            if (!isset($this->tree[$parts[1]])) {
                $this->tree[$parts[1]] = [
                    'url' => '/' . $parts[1],
                    'title' => '',
                    'urls' => 0,
                    'sub' => [],
                    'urlsList' => [],
                ];
            }
            $this->tree[$parts[1]]['urls']++;
            $this->tree[$parts[1]]['urlsList'][] = $url;

            // second level tree
            if (!empty($parts[2])) {
                if (!isset($this->tree[$parts[1]]['sub'][$parts[2]])) {
                    $this->tree[$parts[1]]['sub'][$parts[2]] =[
                        'url' => '/' . $parts[1] . '/' . $parts[2],
                        'title' => '',
                        'urls' => 0,
                        'sub' => [],
                        'urlsList' => [],
                    ];
                }
                $this->tree[$parts[1]]['sub'][$parts[2]]['urls']++;
                $this->tree[$parts[1]]['sub'][$parts[2]]['urlsList'][] = $url;
            }
        }

        // clean nodes and subnodes, that have too few items
        foreach ($this->tree as $index => $node) {
            if ($node['urls'] < self::MIN_SECTIONS) {
//                $this->orphans[] = $this->tree[$index]['url'];
                $this->orphans = array_merge($this->orphans, $this->tree[$index]['urlsList']);
                unset($this->tree[$index]);
                continue;
            }
            foreach ($node['sub'] as $subIndex => $sub) {
                if ($sub['urls'] < $minSubSections) {
                    unset($this->tree[$index]['sub'][$subIndex]);
                    continue;
                }
            }
        }
//        echo '<p>Orphans</p>';
//        print_r($this->orphans);
//
//        echo '<p>Tree</p>';
//        print_r($this->tree);
    }

    /**
     * @return array
     */
    protected function getSimilarOrphans()
    {
//        echo '<p>Orphans</p>';
//        print_r($this->orphans);
        $similar = [];
        sort($this->orphans);
        if (count($this->orphans) < 2) {
            return [];
        }

        // compare each orphan to the next one and count matched letters
        $minLettersMatched = 5;
        $loopSize = count($this->orphans) - 1;
        $lettersMatchingByOrphan = array_fill(0, $loopSize, 0);
        for ($index = 0; $index < $loopSize; $index++) {
            $lettersMatched = 0;
            $length = min([10, mb_strlen($this->orphans[$index]), mb_strlen($this->orphans[$index + 1])]);
            for ($charIndex = 0; $charIndex < $length; $charIndex++) {
                if ($this->orphans[$index]{$charIndex} != $this->orphans[$index + 1]{$charIndex}) {
                    continue 2;
                }
                $lettersMatched++;
            }
            $lettersMatchingByOrphan[$index] = ($lettersMatched >= $minLettersMatched ? $lettersMatched : 0);
        }

        // calculate weight of each
        // $matchWeights = array_fill(0, $loopSize, 0);
        $matchLengths = array_fill(0, $loopSize, 0);
        for ($index = 0; $index < $loopSize; $index++) {
            if ($lettersMatchingByOrphan[$index] == 0) {
                $this->notSimilarOrphans[] = $this->orphans[$index];
                continue;
            }
            $matchStart = $matchEnd = $index;
            for ($i = $index + 1; $i < $loopSize; $i++) {
                if ($lettersMatchingByOrphan[$i] == 0) {
                    break;
                }
                $matchEnd = $i;
            }
            // if we have 2 similar ones, ($matchEnd - $matchStart) will be = 0, because second one will have 0 letters matched
            // since he is compared to third. if we have 3 similar, ($matchEnd - $matchStart) will be 1. this is why we need to add 2
            $matchLength = $matchEnd - $matchStart + 2;
            $minMatch = min(array_slice($lettersMatchingByOrphan, $matchStart, $matchLength - 1));
            // $matchWeight = $matchLength * 1.25 + $minMatch * 0.9; // length is more important than match size
            // $matchWeights[$index] = $matchWeight;
            $matchLengths[$index] = $matchLength;

            // if match length is too small, ignore it and add to notSimilarOrphans
            if ($matchLength > self::MIN_SECTIONS) {
                $similar[] = [
                    'title' => mb_substr($this->orphans[$index], 0, $minMatch),
                    'urls' => $matchLength,
                    'urlsList' => array_slice($this->orphans, $index, $matchLength),
                ];
            } else {
                $this->notSimilarOrphans = array_merge(
                    $this->notSimilarOrphans, array_slice($this->orphans, $matchStart, $matchLength)
                );
            }

            // skip match - 1, because loop itself will add 1 more
            $index += ($matchLength - 1);
        }

        // as an edge case, if last $lettersMatchingByOrphan is 0, then next (last) orphan is also a not similar orphan
        // (if it's not 0, last element would be added as a part of a match)
        if (!$lettersMatchingByOrphan[$loopSize - 1]) {
            $this->notSimilarOrphans[] = $this->orphans[$loopSize];
        }

//        echo '<p>Similar</p>';
//        print_r($similar);
//
//        echo '<p>Not similar</p>';
//        print_r($this->notSimilarOrphans);
//
//        echo '<p>All orphans</p>';
//        print_r($this->orphans);

        return $similar;
    }

    /**
     * @param bool $withFullUrlsList
     * @return array
     */
    public function getSections($withFullUrlsList = false)
    {
        $this->prepareTree();

        // extract obvious sections
        $sections = [];
        foreach ($this->tree as $part => $node) {
            $section = [
                'title' => '/' . $part,
                'code' => '/' . $part,
                'url' => $node['url'],
                'urls' => $node['urls'],
                'sub' => $node['sub'],
                'urlsList' => [],
            ];

            if ($withFullUrlsList) {
                $section['urlsList'] = $node['urlsList'];
            }

            $sections[] = $section;
        }

        // extract similar pages
        $similars = $this->getSimilarOrphans();
        foreach ($similars as $similar) {
            $section = [
                'title' => $similar['title'],
                'code' => $similar['title'],
                'url' => null,
                'urls' => $similar['urls'],
                'sub' => [],
                'urlsList' => [],
            ];

            if ($withFullUrlsList) {
                $section['urlsList'] = $similar['urlsList'];
            }

            $sections[] = $section;
        }

        // show not similar orphans
        if ($this->notSimilarOrphans) {
            $section = [
                'title' => 'Остальные страницы',
                'code' => 'orphans',
                'url' => null,
                'urls' => count($this->notSimilarOrphans),
                'sub' => [],
            ];

            if ($withFullUrlsList) {
                $section['urlsList'] = $this->notSimilarOrphans;
            }

            $sections[] = $section;
        }

        return $sections;
    }
}
