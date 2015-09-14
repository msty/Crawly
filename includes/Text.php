<?php

/**
 * Class Text
 */
class Text
{
    /**
     * @var string
     */
    protected $content;

    /**
     * @var array
     */
    protected $words;

    /**
     * @var array
     */
    protected $wordsBySentence = [];

    /**
     * @var array
     */
    protected $sentences = [];

    /**
     * @var bool
     */
    protected $changed = true;

    /**
     * @var int
     */
    protected $length = 0;

    /**
     * @var int
     */
    protected $wordCount = 0;

    public function __construct($content = '')
    {
        $this->setContent($content);
    }

    public function setContent($content)
    {
        $this->content = trim($content);
    }

    public function getContent()
    {
        return $this->content;
    }

    protected function onChange()
    {
        if (!$this->changed) {
            return;
        }
        $this->length = mb_strlen($this->content);
        $this->words = explode(' ', $this->content);
        $this->wordCount = count($this->words);
        $this->changed = false;
    }

    public function length()
    {
        $this->onChange();
        return $this->length;
    }

    public function wordCount()
    {
        $this->onChange();
        return $this->wordCount;
    }

    /**
     * приводим тексты к базовой форме
     */
    public function lemmatize()
    {
        $this->content = (new Morpho())->lemmatize_text($this->content);
    }

    public function toSentences()
    {
        if (!empty($this->sentences)) {
            return $this->sentences;
        }
        $w1 = array();
        $s1 = explode(".", $this->getContent());
        array_walk($s1, 'trim_array');
        $s1 = array_values(array_filter($s1));
        $s1c = count($s1);
        if (empty($s1c)) {
            return array();
        }
        for ($i = 0; $i < $s1c; $i++) {
            $w1[$i] = explode(" ", $s1[$i]);
        }

        // Объединяем короткие предложения и разбиваем длинные
        $chunk_size = 10;
        for ($i = $s1c - 1; $i >= 0; $i--) {
            // объединяем
            if ($i > 0 and (count($w1[$i]) < $chunk_size or count($w1[$i - 1]) < $chunk_size)) {
                $w1[$i - 1] = array_merge($w1[$i - 1], $w1[$i]);
                unset($w1[$i]);
            }
            // разбиваем
            else if (count($w1[$i]) >= 18) {
                $ol = count($w1[$i]) % $chunk_size;
                $ols = array_fill(0, floor( count($w1[$i]) / $chunk_size ), $chunk_size);
                $ols_c = count($ols);
                while ($ol > 0) {
                    for ($j = 0; $j < $ols_c; $j++)
                    {
                        $ols[$j]++;
                        if (--$ol <= 0)
                            break 2;
                    }
                }
                $insert = array();
                $olp = 0;
                for ($j = 0; $j < $ols_c; $j++) {
                    $insert[] = array_slice($w1[$i], $olp, $ols[$j]);
                    $olp += $ols[$j];
                }
                array_splice($w1, $i, 1, $insert);
            }
        }
        $w1 = array_values($w1);
        $w1c = count($w1);

        // Обновляем массив предложений
        unset($s1); $s1 = array();
        for ($i = 0; $i < $w1c; $i++) {
            $s1[$i] = implode(" ", $w1[$i]);
        }
        array_walk($s1, 'trim_array');
        $s1 = array_filter($s1);
        $this->wordsBySentence = $w1;
        $this->sentences = $s1;
        return $s1;
    }

    public function getWordsBySentence()
    {
        if (empty($this->sentences)) {
            $this->toSentences();
        }
        return $this->wordsBySentence;
    }

    public function cutLength($length)
    {
        $thresholdLength = 0.9 * $length;
        if ($this->length() > $length) {
            $cutMark = mb_strpos($this->content, " ", $thresholdLength);
            $this->content = mb_substr($this->content, 0, $cutMark < $length ? $cutMark : $length);
        }
        $this->changed = true;
    }

    public function replaceTransliteratedLetters()
    {
        // не обрабатываем украинские тексты
        if (preg_match("|[ҐґЇїЄє]|u", $this->content)) {
            return;
        }

        $words = explode(" ", $this->content);
        $wordsCount = count($words);
        for ($i = 0; $i < $wordsCount; $i++) {
            if (preg_match("|[a-z]|iu", $words[$i]) and preg_match("|[а-я]|iu", $words[$i])) {
                $words[$i] = $this->fixTransliteratedWord($words[$i]);
            }
        }
        $this->content = implode(" ", $words);
        $this->changed = true;
    }

    protected function fixTransliteratedWord($word) {
        $replacement = array(
            "t" => "т", "h" => "н", "b" => "в", "m" => "м",
            "e" => "е", "o" => "о", "p" => "р", "a" => "а", "k" => "к", "c" => "с",
            "r" => "г", "y" => "у", "u" => "и"
        );
        return strtr($word, $replacement);
    }

    public function cleanContent()
    {
        $this->removeQuotes();
        $this->replaceSymbols();
        $this->removeTags();
        $this->removeMeaninglessWords();
        $this->content = preg_replace("|\s{2,}|i", " ", $this->content);
        $this->changed = true;
    }

    public function removeQuotes($light = false)
    {
        $replacements = $light
            ? ["\'", '\"', "'", '"', "«", "»", "“", "”", "„", "‘", "’"]
            : [
                "&#34;", "\'", '\"', "'", '"', "&quot;", "&#171;", "&laquo;", "«", "&#187;", "&raquo;", "»",
                "&#8220;", "“", "&#8221;", "”", "&#8222;", "„", "&#8242;", "?", "&#8243;", '?', "&#8216;", "‘", "&#8217;", "’", "&#8218;", "‚"
            ];
        $this->content = str_replace($replacements, "", $this->content);
        $this->changed = true;
    }

    public function replaceSymbols()
    {
        $this->content = str_ireplace("&nbsp;", " ", $this->content);
        $this->content = preg_replace("|&[^;]{1,6};|isu", "", $this->content);
        $this->content = str_replace("\r\n", " ", mb_strtolower($this->content));
        $this->content = str_replace("\n", " ", $this->content);
        $this->content = str_replace("(", " ", $this->content);
        $this->content = str_replace(")", " ", $this->content);
        $this->content = str_replace(array('…', '?', '!', '...'), '.', $this->content);
        $this->content = str_replace(",", " ", $this->content);
        // $this->content = str_replace(".", " ", $this->content);
        $this->changed = true;
    }

    public function removeTags()
    {
        $this->content = preg_replace("|<br\s*/?\s*>|isu", " ", $this->content);
        $this->content = preg_replace("|<[^>]*>|isu", " ", $this->content);
        $this->changed = true;
    }

    public function removeMeaninglessWords()
    {
        $ukr = 'АаБбВвГгҐґДдЕеЄєЖжЗзИиІіЇїЙйКкЛлМмНнОоПпРрСсТтУуФфХхЦцЧчШшЩщЬьЮюЯя';
        $this->content = preg_replace("#(^|\s)[^a-zA-Zа-яА-Я0-9" . $ukr . "]+($|\s)#isu", " ", $this->content);
        $this->changed = true;
    }
}
