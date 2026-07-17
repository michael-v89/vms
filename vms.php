<?php

declare (strict_types=1);

(new App())->run($argv);
return;

class App
{
    private string $lang = '_';

    public function run($argv): void
    {
        $arg1 = $argv[1] ?? null;
        $params = [];
        if (in_array($argv[1], ['A', 'B'])) {
            $this->lang = $arg1;
            $action = $argv[2] ?? null;
            $params = array_slice($argv, 3);
        } else {
            $action = $argv[1] ?? null;
            $params = array_slice($argv, 2);
        }

        if ($action === null) return;


        $this->init();
        $this->$action(...$params);
    }

    public function init(): void
    {
        $this->loadContent();
        $this->calcWordStat();
    }


    private string $vmsFilename = 'vms.txt';

    /** @var array<string, string[][]> page => page rows */
    private array $rows = [];

    private function loadContent(): void
    {
        $content = file_get_contents($this->vmsFilename);
        $textRows = explode("\n", trim($content));

        // they don't look like text, more like random letters
        $skippedParts = [
            '49v.L',
            '57v.R2',
            '57v.R4',
            '66r.M',
            '69r.W',
            '75v.K',
            '76r.L',
        ];
        $skippedParts = array_combine($skippedParts, $skippedParts);

        foreach ($textRows as $textRow) {
            $textRow = trim($textRow);

            preg_match('/\<f(\d+[a-z])(\.[\w0-9]+)?.+\>\s+/', $textRow, $matches);
            $page = $matches[1];

            $partId = trim($matches[1]) . ($matches[2] ?? '');
            if (isset($skippedParts[$partId])) continue;

            $isLangA = $this->isLangA($page);
            if ($this->lang === 'B' && $isLangA) continue;
            else if ($this->lang === 'A' && !$isLangA) continue;

            $textRow = $this->normalizeRow($textRow);

            $rowWords = explode('.', $textRow);
            $row = [];
            foreach ($rowWords as $word) {
                $word = trim($word);
                $word = strtolower($word);

                if (strlen($word) >= 43) continue;  // don't look like words
                if ($word === '') continue;

                $row[] = $word;
            }

            $this->rows[$page][] = $row;
        }
    }

    private function isLangA(string $page): bool
    {
        return in_array($page, $this->langAPages);
    }

    private function normalizeRow(string $str): string
    {
        // remove line info
        $str = preg_replace('/<.+>\s+/', '', $str);

        $str = str_replace('=', '', $str);
        $str = str_replace('-', '.', $str);

        // looks like unclear writing of sh
        $str = str_replace('csh', 'sh', $str);
        $str = str_replace('cs', 'sh', $str);

        return $str;
    }

    private function incr(array &$data, string $key): void
    {
        $data[$key] = ($data[$key] ?? 0) + 1;
    }


    /** @var array<string, int> word => count in text */
    private array $wordCnt = [];

    /** @var array<string, int> word => count in text */
    private array $unclearWords = [];

    /** @var array<string, string[]> word => pages */
    private array $wordPages = [];

    /** @var array<string, string[]> word => letters */
    private array $wordLetters = [];

    /** @var array<string, int> letter => order (initialized in runtime) */
    private array $letterOrder = [];

    private array $letters = [
        'a',
        'i',
        'ii',
        'iii',
        'o',
        'e',
        'ee',
        'eee',
        'eeee',

        'd',
        'l',
        'r',
        's',

        'k',
        't',
        'f',
        'p',

        'q',  // mostly in beginning
        'y',  // mostly in beginning or end, rarely in between
        'n',  // mostly in the end
        'x',  // rare letter

        'm',  // in the end, probably mark of short word
        'g',  // in the end, probably mark of short word

        'ch',
        'sh',

        'ckh',
        'cth',
        'cfh',
        'cph',

        'ckhh',
        'cthh',
        'cfhh',
        'cphh',

        'ikh',
        'ith',
        'ifh',
        'iph',

        'ikhh',
        'ithh',
        'ifhh',
        'iphh',
    ];

    // proceedings-of-a-seminar-30-november-1976.pdf
    private array $langAPages = [
        '1r', '2r', '3r', '4r', '5r', '6r', '7r', '8r', '9r', '10r',
        '1v', '2v', '3v', '4v', '5v', '6v', '7v', '8v', '9v', '10v',
        '11r', '13r', '14r', '15r', '16r', '17r', '18r', '19r', '20r',
        '11v', '13v', '14v', '15v', '16v', '17v', '18v', '19v', '20v',
        '21r', '22r', '23r', '24r', '25r', '27r', '28r', '29r', '30r',
        '21v', '22v', '23v', '24v', '25v', '27v', '28v', '29v', '30v',
        '32r', '35r', '36r', '37r', '38r',
        '32v', '35v', '36v', '37v', '38v',
        '42r', '44r', '45r', '47r', '49r',
        '42v', '44v', '45v', '47v', '49v',
        '51r', '52r', '53r', '54r', '56r', '58r',
        '51v', '52v', '53v', '54v', '56v', '57v', '58v',  // correct
        '87r', '88r', '89r1', '89r2', '90r1', '90r2',
        '87v', '88v', '89v1', '89v2', '90v1', '90v2',
        '93r', '96r', '99r', '100r',
        '93v', '96v', '99v', '100v',
        '101r1', '101r2', '102r1', '102r2',
        '101v1', '101v2', '102v1', '102v2',
    ];

    private function wordIterator(): Generator
    {
        foreach ($this->rows as $page => $pageRows) {
            foreach ($pageRows as $row) {
                foreach ($row as $word) {
                    yield [$page, $word];
                }
            }
        }
    }

    private function calcWordStat(): void
    {
        $this->wordCnt = [];
        $this->unclearWords = [];
        $this->wordPages = [];

        foreach ($this->wordIterator() as [$page, $word]) {
            $this->wordPages[$word][] = $page;

            if ($this->isRecognitionError($word)) {
                $this->incr($this->unclearWords, $word);
            } else {
                $this->incr($this->wordCnt, $word);
            }
        }

        unset($this->wordCnt['n']);  // only end of lines, maybe just filler
        unset($this->wordCnt['oiiiin']);  // looks like aiiin
        unset($this->wordCnt['diiiin']);  // looks like daiin

        arsort($this->wordCnt);
        arsort($this->unclearWords);
        ksort($this->wordPages);

        $this->letterOrder = array_flip($this->letters);

        // length desc
        usort($this->letters, static fn($a, $b) => strlen($b) <=> strlen($a));

        $this->wordLetters = [];
        foreach ($this->wordCnt as $word => $cnt) {
            $letters = $this->splitByLetters($word);

            if (count($letters) === 0) {
                // only by 1 word, most look like incorrect recognition
                unset($this->wordCnt[$word]);
                continue;
            }

            $this->wordLetters[$word] = $letters;
        }
    }

    private function isRecognitionError(string $word): bool
    {
        return !preg_match('/^[a-z]+$/', $word);
    }

    /** @return string[] */
    private function splitByLetters(string $word): array
    {
        $wordLetters = [];

        $letters = array_combine($this->letters, $this->letters);
        $letters['i'] .= '(?![ktfp]h)';
        $letters['ii'] .= '(?![ktfp]h)';
        $letters['iii'] .= '(?![ktfp]h)';
        $letterRegexp = '/^(' . implode('|', $letters) . ')/';

        while ($word !== '') {
            $foundLetter = null;

            $matches = [];

            preg_match($letterRegexp, $word, $matches);
            if (isset($matches[1])) {
                $foundLetter = $matches[1];
                $word = substr($word, strlen($foundLetter));
            }

            if ($foundLetter === null) {
                $wordLetters = [];
                break;
            }

            $wordLetters[] = $foundLetter;
        }

        return $wordLetters;
    }

    private function length(string $word): int
    {
        return count($this->wordLetters[$word]);
    }

    private function writeln($str, $level = 0): void
    {
        echo str_repeat(' ', $level * 4) . $str . "\n";
    }


    public function showWordFrequency(): void
    {
        $totalCnt = array_sum($this->wordCnt);
        foreach ($this->wordCnt as $word => $cnt) {
            $this->writeln(sprintf('%-10s  %-4d  %6.2f', $word, $cnt, $cnt * 100 / $totalCnt));
        }
    }

    public function showWordPages(): void
    {
        foreach ($this->wordPages as $word => $wordPages) {
            $this->writeln($word. ':  ' . implode(', ', $wordPages));
        }
    }

    public function showUnclearWords(): void
    {
        foreach ($this->unclearWords as $word => $cnt) {
            $this->writeln($cnt. ' ' . $word);
        }
    }

    public function showWordCntSorted(string $minCnt = '1'): void
    {
        $minCnt = (int) $minCnt;

        $wordCnt = $this->wordCnt;
        $this->sort($wordCnt, true);

        foreach ($wordCnt as $word => $cnt) {
            if ($cnt < $minCnt) continue;

            $this->writeln($word . str_repeat(' ', 42 - strlen((string) $cnt) - strlen($word)) . $cnt);
        }
    }

    public function showWordCntSortedRev(string $minCnt = '1'): void
    {
        $minCnt = (int) $minCnt;

        $wordCnt = $this->wordCnt;

        uksort($wordCnt, function (string $a, string $b) {
            $aRev = implode('', array_reverse($this->wordLetters[$a]));
            $bRev = implode('', array_reverse($this->wordLetters[$b]));

            return $this->compareWords($aRev, $bRev);
        });

        foreach ($wordCnt as $word => $cnt) {
            if ($cnt < $minCnt) continue;
            $this->writeln($cnt . str_repeat(' ', 42 - strlen((string) $cnt) - strlen($word)) . $word);
        }
    }


    public function showLetterFrequency(): void
    {
        $totalCnt = 0;
        $letterStat = [];
        foreach ($this->wordCnt as $word => $cnt) {
            $wordLetters = $this->wordLetters[$word];

            foreach ($wordLetters as $i => $letter) {
                if ($letter === null) break;
                $letter = $this->simplifyRepeating($letter);

                $letterStat[$letter] = ($letterStat[$letter] ?? 0) + $cnt;
                $totalCnt += $cnt;
            }
        }

        arsort($letterStat);

        $i = 0;
        foreach ($letterStat as $letter => $cnt) {
            $i++;
            $this->writeln(sprintf('%2d    %-4s    %6d    %8.2f', $i, $letter, $cnt, $cnt * 100 / $totalCnt));
        }
        $this->writeln($totalCnt);
    }

    public function showLetterFrequencyAll(): void
    {
        $totalCnt = ['_' => 0, 'A' => 0, 'B' => 0];
        $letterStat = ['_' => [], 'A' => [], 'B' => []];
        foreach ($this->wordIterator() as [$page, $word]) {
            $wordLetters = $this->wordLetters[$word] ?? [];
            $isLangA = $this->isLangA($page);

            foreach ($wordLetters as $i => $letter) {
                if ($letter === null) break;

                $letter = $this->simplifyRepeating($letter);

                $this->incr($letterStat['_'], $letter);
                $totalCnt['_']++;

                if ($isLangA) {
                    $this->incr($letterStat['A'], $letter);
                    $totalCnt['A']++;
                } else {
                    $this->incr($letterStat['B'], $letter);
                    $totalCnt['B']++;
                }
            }
        }

        arsort($letterStat['_']);
        arsort($letterStat['A']);
        arsort($letterStat['B']);

        $tKeys = array_keys($letterStat['_']);
        $aKeys = array_keys($letterStat['A']);
        $bKeys = array_keys($letterStat['B']);
        for ($i = 0; $i < count($letterStat['_']); $i++) {
            $tLetter = $tKeys[$i];
            $tCnt = $letterStat['_'][$tLetter];
            $aLetter = $aKeys[$i] ?? '';
            $aCnt = $letterStat['A'][$aLetter] ?? 0;
            $bLetter = $bKeys[$i] ?? '';
            $bCnt = $letterStat['B'][$bLetter] ?? 0;

            $str = sprintf('| %-4s  %6.2f | %-4s  %6.2f | %-4s  %6.2f |',
                $tLetter, $tCnt * 100 / $totalCnt['_'],
                $aLetter, $aCnt * 100 / ($totalCnt['A'] ?: 1),
                $bLetter, $bCnt * 100 / ($totalCnt['B'] ?: 1),
            );
            $this->writeln($str);
        }
    }

    public function showNextLetters(): void
    {
        $letters = [];
        foreach ($this->wordCnt as $word => $cnt) {
            $wordLetters = $this->wordLetters[$word] ?? [];

            for ($i = -1; $i < count($wordLetters); $i++) {
                $letter = $i === -1 ? '^' : $wordLetters[$i];
                $nextLetter = $wordLetters[$i + 1] ?? '_';

                $letter = $this->simplifyRepeating($letter);
                $nextLetter = $this->simplifyRepeating($nextLetter);

                $letters[$letter] = $letters[$letter] ?? [];
                $this->incr($letters[$letter], $nextLetter);
            }
        }

        $this->sort($letters, true);

        foreach ($letters as $letter => $nextLetters) {
            arsort($nextLetters);
            $data = $this->keymap($nextLetters, static fn(string $letter, int $cnt) => sprintf('%4s', $letter) . ': ' . sprintf('%-4s', $cnt));
            $str = implode(' | ', $data) . ' |';
            $this->writeln(sprintf('| %-10s | ', $letter) . $str);
        }
    }

    public function showPrevLetters(): void
    {
        $letters = [];
        foreach ($this->wordCnt as $word => $cnt) {
            $wordLetters = $this->wordLetters[$word] ?? [];

            $wordLetters = array_reverse($wordLetters);
            for ($i = -1; $i < count($wordLetters); $i++) {
                $letter = $i === -1 ? '_' : $wordLetters[$i];
                $prevLetter = $wordLetters[$i + 1] ?? '^';

                $letter = $this->simplifyRepeating($letter);
                $prevLetter = $this->simplifyRepeating($prevLetter);

                $letters[$letter] = $letters[$letter] ?? [];
                $this->incr($letters[$letter], $prevLetter);
            }
        }

        $this->sort($letters, true);

        foreach ($letters as $letter => $prevLetters) {
            arsort($prevLetters);
            $data = $this->keymap($prevLetters, static fn($letter, $cnt) => sprintf('%4s', $letter) . ': ' . sprintf('%-4s', $cnt));
            $str = implode(' | ', $data) . ' |';
            $this->writeln(sprintf('| %-10s | ', $letter) . $str);
        }
    }

    private function simplifyRepeating(string $letter): string
    {
        if (in_array($letter, ['ee', 'eee', 'eeee'])) $letter = 'e';
        if (in_array($letter, ['ii', 'iii'])) $letter = 'i';

        return $letter;
    }

    /** @param string[] | array<string, mixed> $array */
    private function sort(array &$array, bool $byKey): void
    {
        if ($byKey) {
            uksort($array, [$this, 'compareWords']);
        } else {
            uasort($array, [$this, 'compareWords']);
        }
    }

    private function compareWords(string $a, string $b): int
    {
        $lettersA = $this->wordLetters[$a] ?? $this->splitByLetters($a);
        $lettersB = $this->wordLetters[$b] ?? $this->splitByLetters($b);

        foreach ($lettersA as $n => $letterA) {
            $letterB = $lettersB[$n] ?? null;
            if ($letterB === null) continue;
            $res = $this->letterOrder[$letterA] <=> $this->letterOrder[$letterB];
            if ($res !== 0) {
                return $res;
            }
        }

        return count($lettersA) <=> count($lettersB);
    }

    private function keymap(array $array, callable $callback): array
    {
        $res = [];
        foreach ($array as $key => $value) {
            $res[] = $callback($key, $value);
        }

        return $res;
    }


    public function showBigrams(): void
    {
        $bigrams = [];
        $prevWord = null;
        foreach ($this->wordIterator() as [$page, $word]) {
            if ($this->isRecognitionError($word)) {
                $prevWord = null;
                continue;
            }

            if ($prevWord !== null) {
                $str = $prevWord . ' ' . $word;
                $this->incr($bigrams, $str);
            }

            $prevWord = $word;
        }

        arsort($bigrams);

        foreach ($bigrams as $bigram => $cnt) {
            if ($cnt === 1) continue;

            $this->writeln(sprintf('%-30s  %10d', $bigram, $cnt));
        }
    }

    public function showDoubleWords(): void
    {
        $bigrams = [];
        $prevWord = null;
        foreach ($this->wordIterator() as [$page, $word]) {
            if ($this->isRecognitionError($word)) {
                $prevWord = null;
                continue;
            }

            if ($prevWord !== null) {
                if ($prevWord === $word) {
                    $str = $prevWord . ' ' . $word;
                    $this->incr($bigrams, $str);
                }
            }

            $prevWord = $word;
        }

        arsort($bigrams);

        foreach ($bigrams as $bigram => $cnt) {
            if ($cnt === 1) continue;

            $this->writeln(sprintf('%-30s  %10d', $bigram, $cnt));
        }
    }


    public function showOneLetterWords(): void
    {
        foreach ($this->letterOrder as $letter => $order) {
            $cnt = $this->wordCnt[$letter] ?? null;
            if (!$cnt) continue;

            $this->writeln(sprintf('%-10s  %-4d', $letter, $cnt));
        }
    }

    public function showWordLength(): void
    {
        $stat = [];
        foreach ($this->wordLetters as $word => $letters) {
            // they don't look like words in a text
            if (in_array($word, ['a', 'k', 'm', 'g', 'x', 'ch', 'cth'])) continue;

            $length = count($letters);
            $this->incr($stat, (string) $length);
        }

        ksort($stat);
        foreach ($stat as $length => $cnt) {
            $str = sprintf('%-10s  %10s', $length, $cnt);
            $this->writeln($str);
        }
    }


    public function showWordsOnlyInHerbalSection(): void
    {
        $herbalSectionWords = [];

        foreach ($this->wordPages as $word => $pages) {
            $cntInOtherSections = 0;
            foreach ($pages as $page) {
                $isHerbalSection = (((int) $page) <= 57);
                if (!$isHerbalSection) {
                    $cntInOtherSections++;
                }
            }

            if ($cntInOtherSections <= 0) {
                $cnt = $this->wordCnt[$word] ?? null;
                if ($cnt !== null) {
                    $herbalSectionWords[$word] = $cnt;
                }
            }
        }

        arsort($herbalSectionWords);

        foreach ($herbalSectionWords as $word => $cnt) {
            echo $word . ' | ' . $cnt . ' | ' . implode(', ', $this->wordPages[$word]) . "\n";
        }
    }


    public function showFrequentWordRows($minCnt = '80'): void
    {
        $minCnt = (int) $minCnt;

        foreach ($this->rows as $page => $pageRows) {
            foreach ($pageRows as $n => $row) {
                $sumFrequency = 0;
                $cntWords = 0;
                $uniqWords = [];
                $words = [];

                foreach ($row as $word) {
                    if ($this->isRecognitionError($word)) { $cntWords = 0; break; }

                    $cnt = $this->wordCnt[$word] ?? 0;
                    if ($cnt < $minCnt) { $cntWords = 0; break; }

                    $sumFrequency += $cnt;
                    $cntWords++;

                    $uniqWords[$word] = 1;
                    $words[] = $word;
                }

                if ($cntWords === 0) continue;

                $avgFrequency = $sumFrequency / $cntWords;

                $lineStat[] = [
                    'sumFrequency' => $sumFrequency,
                    'avgFrequency' => $avgFrequency,
                    'uniqWordCnt' => count($uniqWords),
                    'uniqWords' => $uniqWords,
                    'words' => $words,
                    'page' => $page,
                    'n' => $n,
                ];
            }
        }

        usort($lineStat, function ($a, $b) {
            return $b['sumFrequency'] <=> $a['sumFrequency'];
        });


        $allUniqWords = [];
        $totalWords = 0;
        for ($i = 0; $i < 10; $i++) {
            $line = $lineStat[$i];
            $n = $line['n'];
            $page = $line['page'];
            $row = $this->rows[$page][$n];

            $this->writeln(sprintf("%-3s  %-6s  %4d  %-6.2f  %s", ($i + 1) . ':', $page, $line['sumFrequency'], $line['avgFrequency'], $line['uniqWordCnt']));
            $this->writeln(implode('.', $row));
            $this->writeln('');

            $totalWords += count($line['words']);
            foreach ($line['uniqWords'] as $word => $tmp) {
                $allUniqWords[$word] = $this->wordCnt[$word] ?? 0;
            }
        }

        arsort($allUniqWords);

        $this->writeln('');
        foreach ($allUniqWords as $word => $cnt) {
            $this->writeln(sprintf("%-20s %4d", $word, $cnt));
        }

        echo 'Unique words: ' . count($allUniqWords) . "\n";
        echo 'Total words: ' . $totalWords . "\n";
    }
}
