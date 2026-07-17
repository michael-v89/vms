# Scripts for text analysis of Voynich Manuscript and Wikipedia

Details:\
https://habr.com/ru/articles/1060026/




## Program for Voynich Manuscript

Run:
```
php vms.php [lang] [action] [params]
lang - A|B
action - any public method from vms.php
```

Examples:
```
php vms.php showLetterFrequency
php vms.php A showLetterFrequency
php vms.php B showLetterFrequency

php vms.php showNextLetters
php vms.php showPrevLetters
php vms.php showLetterFrequency
php vms.php showWordFrequency

php vms.php showDoubleWords
php vms.php showOneLetterWords
php vms.php showWordLength
php vms.php showWordsOnlyInHerbalSection

php vms.php showWordCntSorted
php vms.php showWordCntSortedRev
php vms.php showWordCntSorted 2
php vms.php showWordCntSortedRev 2

php vms.php showFrequentWordRows
php vms.php showFrequentWordRows 80
```




## Program for Wikipedia

Download an convert Unicode symbol database. It will create a file "unicode_database.json".
```
curl --remote-name \
https://www.unicode.org/Public/UCD/latest/ucdxml/ucd.all.flat.zip

php wiki.php convertUnicodeDatabase
```


Download Wikipedia dump on selected languange.\
https://mirror.accum.se/mirror/wikimedia.org/dumps/\
Files like "pages-articles-multistream*".

Extract XML from BZ2.

Now you can get statistics for this language:
```
php wiki.php wordStat xmlFile locale symbolNamespace wordCnt articleNumberFrom-articleNumberTo
```

`locale` is PHP locale to process text.\
You can get available locales using this command:
```
php wiki.php showLocales
```

`symbolNamespace` corresponds to field "na" in "unicode_database.json".\
Only words consisting of the symbols with this namespace will be used in statistics.\
It can be a regexp. If a group is present, it is used as a letter for transliteration.\
You can use it if standard transliteration does not work.

`wordCnt` - count of most frequent words in output, for example "1000".

`articleNumberFrom-articleNumberTo` - article range, for example "2000-4000".


Some examples:
```
php wiki.php wordStat zhwiki-20260601-pages-articles-multistream3.xml zh 'CJK UNIFIED IDEOGRAPH-#' 1000 2000-4000 > zh_stat.txt

php wiki.php wordStat zh_yuewiki-20260601-pages-articles-multistream.xml yue 'CJK UNIFIED IDEOGRAPH-#' 1000 2000-4000 > zh_yue_stat.txt


php wiki.php wordStat hewiki-20260601-pages-articles-multistream3.xml he 'HEBREW .*' 1000 2000-4000 > he_stat.txt

php wiki.php wordStat hiwiki-20260601-pages-articles-multistream.xml hi 'DEVANAGARI .*|BENGALI .*' 1000 0-200 > hi_stat.txt

php wiki.php wordStat arwiki-20260601-pages-articles-multistream3.xml ar 'ARABIC LETTER (\w).*' 1000 0-200 > ar_stat.txt

php wiki.php wordStat jawiki-20260601-pages-articles-multistream3.xml ja 'HIRAGANA .*|KATAKANA .*|CJK UNIFIED IDEOGRAPH-#' 1000 2000-4000 > ja_stat.txt


php wiki.php wordStat lawiki-20260601-pages-articles-multistream.xml la 'LATIN' 1000 2000-4000 > la_stat.txt

php wiki.php wordStat itwiki-20260601-pages-articles-multistream2.xml it 'LATIN' 1000 2000-4000 > it_stat.txt

php wiki.php wordStat eswiki-20260601-pages-articles-multistream2.xml es 'LATIN' 1000 2000-4000 > es_stat.txt
```
