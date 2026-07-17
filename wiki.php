<?php

declare(strict_types=1);

(new App())->run($argv);
return;

class App
{
    private DOMDocument $domDocument;

    public function run($argv): void
    {
        ini_set('memory_limit', '-1');
        set_time_limit(0);

        $this->domDocument = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors( true );

        array_shift($argv);
        $method = array_shift($argv);
        $args = $argv;

        $this->$method(...$args);
    }

    private function tagIterator(string $text, string $tag, bool $showProgress = true): Generator
    {
        $startTag = '<' . $tag;
        $stopTag = '</' . $tag . '>';
        $fullTagStop = '>';
        $shortTagStop = '/>';

        $cnt = 0;
        $offset = 0;

        $articleTotalCnt = substr_count($text, $startTag);
        while (true) {
            if ($showProgress && $cnt % 100 === 0) fwrite(STDERR, $cnt . ' / ' . $articleTotalCnt . "            \r");

            $posStart = strpos($text, $startTag, $offset);
            if ($posStart === false) break;

            $fullTagStopPos = strpos($text, $fullTagStop, $posStart);
            $shortTagStopPos = strpos($text, $shortTagStop, $posStart);
            if ($fullTagStopPos === false && $shortTagStopPos === false) break;

            $isFullTag = $shortTagStopPos == false || $fullTagStopPos < $shortTagStopPos;
            if ($isFullTag) {
                $posEnd = strpos($text, $stopTag, $posStart);
                $posEnd += strlen($stopTag);
            } else {
                $posEnd = strpos($text, $shortTagStop, $posStart);
                $posEnd += strlen($shortTagStop);
            }

            $offset = $posEnd;

            $articleHtml = substr($text, $posStart, $offset - $posStart);
            yield $cnt => $articleHtml;

            $cnt++;
        }

        if ($showProgress) fwrite(STDERR, $cnt . ' / ' . $articleTotalCnt . "            \n");
    }

    private function extractArticleText(string $articleHtml): string
    {
        $this->domDocument->loadHTML('<?xml encoding="utf-8"?>' . $articleHtml);
        $text = $this->domDocument->textContent;

        $this->domDocument->loadHTML('<html lang=""><meta http-equiv="Content-Type" content="text/html; charset=utf-8">' . $text);
        $text = $this->domDocument->textContent;

        $text = preg_replace('/\[\[[^\]]+?\]\]/ui', '', $text);
        $text = preg_replace('/\[[^\]]+?\]/ui', '', $text);
        $text = preg_replace('/\{\{[^\}]+?\}\}/ui', '', $text);

        return $text;
    }

    public function convertUnicodeDatabase(): void
    {
        $database = $this->loadUnicodeDatabaseFromXml();

        ob_start();
        $this->writeln('{');
        $i = 0;
        foreach ($database as $utf8Str => $data) {
            $key = $this->jsonEncode((string) $utf8Str);

            if ($key === false) continue;

            if ($i !== 0) $this->writeln(',');

            $str = '';
            $str .= sprintf('%-3s', $key . ': ');
            $str .= '{ ';
            $str .= sprintf('"cp": %-8s', $this->jsonEncode($data['cp']) . ',    ');
            $str .= sprintf('"kMandarin": %-20s', $this->jsonEncode($data['kMandarin']) . ', ');
            $str .= sprintf('"kCantonese": %-20s', $this->jsonEncode($data['kCantonese']) . ', ');
            $str .= sprintf('"kSMSZD2003Readings": %-40s', $this->jsonEncode($data['kSMSZD2003Readings']) . ', ');
            $str .= sprintf('"kJapaneseKun": %-40s', $this->jsonEncode($data['kJapaneseKun']) . ', ');
            $str .= sprintf('"kDefinition": %s', $this->jsonEncode($data['kDefinition']) . ',    ');
            $str .= sprintf('"na": %s', $this->jsonEncode($data['na']));
            $str .= ' }';

            $i++;
            echo $str;
        }

        echo "\n";
        $this->writeln('}');

        $str = ob_get_clean();

        file_put_contents('unicode_database.json', $str);
    }

    private function loadUnicodeDatabaseFromXml(): array
    {
        $xml = file_get_contents('zip://ucd.all.flat.zip#ucd.all.flat.xml');

        $database = [];
        foreach ($this->tagIterator($xml, 'char') as $i => $charXml) {
            $codePoint = $this->getAttrValue($charXml,'cp');
            $namespace = $this->getAttrValue($charXml,'na');
            $kCantonese = $this->getAttrValue($charXml,'kCantonese');
            $kMandarin = $this->getAttrValue($charXml,'kMandarin');
            $kDefinition = $this->getAttrValue($charXml,'kDefinition');
            $kSMSZD2003Readings = $this->getAttrValue($charXml,'kSMSZD2003Readings');
            $kJapaneseKun = $this->getAttrValue($charXml,'kJapaneseKun');

            if ($codePoint === null) continue;

            $utf8Str = $this->convertCodePointToUtf8($codePoint);
            if ($utf8Str === null) continue;

            if (isset($database[$utf8Str])) {
                var_dump('Symbol already exists', $codePoint, $utf8Str);
                exit;
            }

            $database[$utf8Str] = [
                'cp' => $codePoint,
                'na' => $namespace,
                'kCantonese' => $kCantonese,
                'kMandarin' => $kMandarin,
                'kSMSZD2003Readings' => $kSMSZD2003Readings,
                'kJapaneseKun' => $kJapaneseKun,
                'kDefinition' => $kDefinition,
            ];
        }

        return $database;
    }

    private function writeln(string $str): void
    {
        echo $str . "\n";
    }

    private function jsonEncode(string|null $str): string|false
    {
        return json_encode($str, JSON_UNESCAPED_UNICODE);
    }

    private function getAttrValue(string $text, string $name): string|null
    {
        $attrValueStart = $name . '="';
        $attrStartPos = strpos($text, $attrValueStart);
        if ($attrStartPos === false) return null;

        $attrStartPos += strlen($attrValueStart);
        $attrEndPos = strpos($text, '"', $attrStartPos);

        $attrValue = substr($text, $attrStartPos, $attrEndPos - $attrStartPos);

        return $attrValue;
    }

    private function convertCodePointToUtf8(string $codePointHex): ?string
    {
        return IntlChar::chr(hexdec($codePointHex));
    }




    private array $unicodeDatabase = [];

    private Transliterator $lowerTransliterator;

    public function wordStat(string $xmlFile, string $locale, string $symbolNamespace, string $wordCnt = '1000', string $articleCnt = '1000'): void
    {
        $json = file_get_contents('unicode_database.json');
        $this->unicodeDatabase = json_decode($json, true, 1024, JSON_THROW_ON_ERROR);

        $transliterator = Transliterator::createFromRules(':: '.$locale.'-Latin; :: Japanese-Latin; :: Hiragana-Latin; :: Katakana-Latin; :: Any-Latin; :: Latin-ASCII; :: NFD; :: [:Nonspacing Mark:] Remove; :: Lower(); :: NFC;', Transliterator::FORWARD);
        if ($transliterator === null) {
            $transliterator = Transliterator::createFromRules(':: Any-Latin; :: Latin-ASCII; :: NFD; :: [:Nonspacing Mark:] Remove; :: Lower(); :: NFC;', Transliterator::FORWARD);
        }

        $this->lowerTransliterator = Transliterator::createFromRules(':: Lower();', Transliterator::FORWARD);

        $wikiFileText = file_get_contents($xmlFile);

        $wordCnt = (int) $wordCnt;

        $parts = explode('-', $articleCnt);
        $articleFrom = count($parts) === 1 ? 0 : (int) $parts[0];
        $articleTo = count($parts) === 1 ? (int) $parts[0] : (int) $parts[1];

        $wordStat = [];
        $wordStatLat = [];
        $nextWordStat = [];
        $sameWordStat = [];

        foreach ($this->tagIterator($wikiFileText, 'text') as $i => $articleHtml) {
            if ($i < $articleFrom) continue;
            if ($i >= $articleTo) break;

            $wordQueue = [];
            $articleText = $this->extractArticleText($articleHtml);

            // remove table markup, because it contains html attributes
            $articleText = preg_replace('/(\{\||\n\s*\||\n\s*!)[^\n]*(?=\n)/u', "", $articleText);

            foreach ($this->wordIterator($articleText, $locale) as $srcWord) {
                $lowercaseSrcWord = $this->lowerTransliterator->transliterate($srcWord);

                $word = '';
                foreach ($this->symbolIterator($lowercaseSrcWord) as $symbol) {
                    $info = $this->unicodeDatabase[$symbol] ?? null;
                    if ($info === null) {
                        // no such symbol, skip
                        continue;
                    }

                    if (!preg_match('/' . $symbolNamespace . '/', $info['na'])) {
                        $word = '';
                        break;
                    }

                    $word .= $symbol;
                }

                if ($word === '') continue;

                $this->incr($wordStat, $word);

                $wordLat = $transliterator->transliterate($word);
//                if (preg_match('/\d+/', $wordLat)) {
//                    var_dump('Word with digits', $word, $wordLat); exit;
//                }

                $wordLat = preg_replace('/\d+/', '', $wordLat);
                $this->incr($wordStatLat, $wordLat);

                if ($wordLat === '') continue;

                // [$unicodeLat, $def] = $this->getWordInfo($word, $locale);
                // $lat = $transliterator->transliterate($word);

                // $lat = preg_replace('/\d+/', '', $lat);
                // $this->incr($wordStatLat, $lat);


                $wordQueue[] = $word;
                if (count($wordQueue) > 2) array_shift($wordQueue);

                if (count($wordQueue) === 2) {
                    $bigram = $wordQueue[0] . ' ' . $wordQueue[1];
                    $this->incr($nextWordStat, $bigram);

                    if ($wordQueue[0] === $wordQueue[1]) {
                        $this->incr($sameWordStat, $bigram);
                    }
                }
            }
        }


        arsort($wordStat);

        $totalCnt = array_sum($wordStat);
        $i = 0;
        foreach ($wordStat as $word => $cnt) {
            $wordLat = $transliterator->transliterate($word);
            $wordLat = $wordLat === $word ? '-' : $wordLat;

            [$lat, $altLat, $def, $symbolLength] = $this->getWordInfo((string)$word, $locale, $symbolNamespace);

            $str = sprintf("%s  %-16s  %-8d  %4.2f      %s  %s  %s",
                $this->mbStrPad($lat, 16),
                $wordLat,
                $cnt, $cnt * 100 / $totalCnt,
                $this->mbStrPad($word, 8, $symbolLength), $altLat, $def,
            );
            $this->writeln($str);

            $i++;
            if ($i >= $wordCnt) break;
        }

        $this->showBigramStat($nextWordStat, 'Next words', 10, $locale, $symbolNamespace);

        $this->showBigramStat($sameWordStat, 'Repeating words', 3, $locale, $symbolNamespace);
    }

    private function incr(array &$data, string $key): void
    {
        $data[$key] = ($data[$key] ?? 0) + 1;
    }

    private function showBigramStat(array $wordCnt, string $message, int $minCnt, string $locale, string $symbolNamespace): void
    {
        $this->writeln("\n\n\n");
        $this->writeln($message);

        ksort($wordCnt);
        arsort($wordCnt);
        foreach ($wordCnt as $bigram => $cnt) {
            if ($cnt < $minCnt) continue;

            $wordQueue = explode(' ', $bigram);

            [$lat1, $altLat1, $def1, $symbolLength1] = $this->getWordInfo($wordQueue[0], $locale, $symbolNamespace);
            [$lat2, $altLat2, $def2, $symbolLength2] = $this->getWordInfo($wordQueue[1], $locale, $symbolNamespace);
            $bigramInfo1 = $lat1 . ' ' . $lat2;
            $bigramInfo2 = $altLat1 . '    ' . $altLat2;

            $bigram .= str_pad(' ', 12 - (mb_strlen($wordQueue[0]) * $symbolLength1 + 1 + mb_strlen($wordQueue[1]) * $symbolLength2));

            $this->writeln(sprintf('%s  %10d    %s  %s', $this->mbStrPad($bigramInfo1, 30), $cnt, $bigram, $bigramInfo2));
        }
    }

    private function mbStrPad(string $str, int $n, $symbolLength = 1): string
    {
        $len = mb_strlen($str, 'UTF-8') * $symbolLength;

        $padRight = true;
        if  ($n < 0) {
            $n = -$n;
            $padRight = false;
        }

        $padLength = $n >= $len ? $n - $len : 0;

        $pad = str_repeat(' ', $padLength);
        $res = $padRight ? $str . $pad : $pad . $str;

        return $res;
    }

    private function getWordInfo(string $word, string $locale, string $na): array
    {
        $latArr = [];
        $altLatArr = [];
        $defArr = [];
        $symbolLength = 1;
        foreach ($this->symbolIterator($word) as $symbol) {
            $data = $this->unicodeDatabase[$symbol];

            $lat = '';
            $altLat = '';
            if (in_array($locale, ['zh', 'yue', 'ja'])) {
                $symbolLength = 2;

                $jap = strtolower($data['kJapaneseKun'] ?? '');

                $altLatData = [
                    'kSMSZD2003Readings' => $data['kSMSZD2003Readings'] ?? null,
                    'kCantonese' => $data['kCantonese'] ?? null,
                    'kMandarin' => $data['kMandarin'] ?? null,
                ];

                if ($locale === 'zh') {
                    $lat = $data['kMandarin'] ?? '';
                    unset($altLatData['kMandarin']);
                } elseif ($locale === 'yue') {
                    $lat = $data['kCantonese'] ?? '';
                    unset($altLatData['kCantonese']);
                } elseif ($locale === 'ja') {
                    $lat = $jap;
                    unset($altLatData['kJapaneseKun']);
                }

                $altLat = '( ' . implode(' | ', array_filter($altLatData)) . ' )';
            } elseif ($locale === 'bo') {
                $str = preg_replace('/^.+\s([\w\-]+)/', '$1', $data['na']);
                $str = strtolower($str);
                $str = preg_replace('/(.+)a$/', '$1', $str);
                $lat = $str;
            } elseif ($locale === 'vi') {
                $lat = '-';
            }

            if ($lat === '') {
                $desc = preg_replace('/' . $na . '/', '$1', $data['na']);
                $desc = trim($desc);
                $lat = strtolower($desc);
            }

            $latArr[] = $lat;
            $altLatArr[] = $altLat;
            $defArr[] = $data['kDefinition'] ?? '?';
        }

        if ($na === 'LATIN') {
            $latArr = [$word];
            $altLatArr = [''];
            $defArr = [''];
        }

        return [
            trim(implode('-', $latArr), '-') ?: '-',
            implode('-', $altLatArr),
            implode(' | ', $defArr),
            $symbolLength,
        ];
    }

    private function symbolIterator(string $word): Generator
    {
        $res = [];

        $len = mb_strlen($word, 'utf-8');
        for ($i = 0; $i < $len; $i++) {
            $symbol = mb_substr($word, $i, 1, 'utf-8');
            yield $i => $symbol;
        }

        return $res;
    }

    private function wordIterator(string $text, string $locale): ?IntlPartsIterator
    {
        $i = IntlBreakIterator::createWordInstance($locale);
        $i->setText($text);

        return $i->getPartsIterator();
    }

    public function showLocales(): void
    {
        $locales = Intlcalendar::getAvailableLocales();
        $this->writeln(implode("\n", $locales));
    }
}
