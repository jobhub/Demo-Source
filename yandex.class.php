<?php
require_once ("logger.class.php");

require_once('curl.class.php');

class yandex  extends curl2
{

    private  $region = 213;
    private  $regionText = 'Москва';



/**
 ИЗБАВИТСЯ И УДАЛИТЬ
 */
    /*
    public function seo_yandex_urls2 ($query, $numdoc = 10){

        $result = $this->getContent("http://www.yandex.ru/yandsearch?stype=www&nl=0&text=".rawurlencode($query)."&numdoc=".$numdoc."&lr=213");


        preg_match_all("#b-serp-item__title-link\" tabindex=\"2\" href=\"(.+)\" onmousedown#isU", $result, $ex);

        $result = array();
        for ($i = 0, $max = count($ex[1]); $i < $max; $i++){
                $result[$i]["page"] = trim(strip_tags($ex[1][$i]));
         }

        return $result;
    }//public function seo_yandex_urls2 ($query, $numdoc = 10){

*/

    /**
     * Получить и распарсить поисковую выдачу яндекса.
     * Пока получает только URL, потом прикрутить сниппеты и прочую лабуду.
     *
     * @param $query запрос к яндексу
     * @param int $numdoc размер выдачи - 1-1000 сайтов
     * @return array
     */
    public  function getParsed($query, $numdoc = 10){

        $j      = 0;
        $page   = 0;
        $result = array();
        $yaNumDoc = 10;
        if($numdoc > 50):
            $yaNumDoc = 50;
        elseif (($numdoc < 50)&&($numdoc >= 30)):
            $yaNumDoc = 30;
        elseif (($numdoc < 30)&&($numdoc >= 20)):
            $yaNumDoc = 20;
        endif;

        do{
            $content = $this->getRaw($query, $yaNumDoc, $page);

            preg_match_all('#<li class="b-serp-item">.*<b class="b-serp-item__number">(.*)</b>.*<a class="b-serp-item__title-link".*href="(.*)".*>(.*)</a>.*<div class="b-serp-item__text">(.*)</div>.*</li>#isU', $content, $ex);


            for ($i = 0, $max = count($ex[1]); $i < $max; $i++):
                    $matches = array();
                    if(preg_match('#<span class="b-serp-url b-serp-url_inline_yes">.*<span class="b-serp-url__item"><a href="/yandsearch.*" class="b-serp-url__link" onmousedown=".*">(.*)</a></span>#isU',$ex[0][$i], $matches)):
                        $result[$j]["region"] = trim($matches[1]);
                    else:
                        $result[$j]["region"] = '';
                    endif;

                    $result[$j]["position"] = (int) trim(strip_tags($ex[1][$i]));
                    $result[$j]["page"]     = trim(strip_tags($ex[2][$i]));
                    $result[$j]["linktext"] = trim(strip_tags($ex[3][$i]));
                    $result[$j]["shippet"]  = trim(strip_tags($ex[4][$i]));
                    $j++;
                    if($j >= ($numdoc-1)):
                        break;
                    endif;
            endfor;

            $page++;
        }while( ($j < ($numdoc-1)) && (preg_match('#<a id="next_page" class="b-pager__next" href=#isU', $content)) );

        return $result;
    }//public  function getParsed($query, $numdoc = 10){



    /**
     * Получить поисковую выдачу яндекса
     *
     * @throws Exception
     * @param $query запрос к яндексу
     * @param int $numdoc размер выдачи - 10-50 сайтов
     * @param int $page номер страницы выдачи
     * @return string
     */
    public function  getRaw($query, $numdoc = 10, $page = 1){

        $query = trim($query);
        if(intval($numdoc) <= 0)
            throw new Exception('Неверное количество документов!');
        if (!$query)
            throw new Exception('Запрос не должен быть пустым!');


        if (preg_match('#yandex\.ru#is', $query)):
            $result = $this->getContent($query);// подразумеваем что тот кто это прислал понимает что к чему
        else:
            $result = $this->getContent("http://www.yandex.ru/yandsearch?p=".$page."&stype=www&nl=0&text=".rawurlencode($query)."&numdoc=".$numdoc."&lr=213");
        endif;

        //$result = $this->getContent($query);

        return $result;
    }//public function  getRaw($query, $numdoc = 10){



    public  function rand_string($len=20) {
        srand((float)microtime() * 1000000);
        $str = strval(rand(1000,9999))."-".strval(rand(1000,9999))."-".strval(rand(1000,9999))."-".strval(rand(1000,9999));
        return substr(md5($str), 0, $len);
    }




    private function getContent($url){
        global $CFG;
        if (!$this->int)
            $this->setInterface(false);
        logger::AddRecord(" получить контент яндекса через интерфейс {$this->int}");
        $rnd = $this->rand_string(10);
        $ban_count = 4;
        do {

            $ex = array();
            $ttt = false;

            if(file_exists($CFG->datadir."/".$this->int."_cookie.txt"))
                $ttt = file_get_contents($CFG->datadir."/".$this->int."_cookie.txt");

            if(!$ttt or  !preg_match('#fuid01#is', $ttt))
                $file = $this->getUrl('http://kiks.yandex.ru/fu');

            sleep(2);
            $file = $this->getUrl($url);
            while (preg_match ("#b-captcha#", $file)){// распознавалка капчи
                    logger::AddRecord("интерфейс {$this->int} BANNED!!!", 100);


					if (preg_match_all ("#name=\"key\" type=\"hidden\" value=\"(.*)\">.*name=\"retpath\" type=\"hidden\" value=\"(.*)\".*<img src=\"(.*)\"#isU", $file, $arr))
					{ $ckey = $arr[1][0]; $cretpath = $arr[2][0]; $cimg = $arr[3][0]; logger::AddRecord("captcha fields extracted [1] ");}

					if (preg_match_all ("#<img src=\"(.*)\".*type=\"hidden\" name=\"key\" value=\"(.*)\".*type=\"hidden\" name=\"retpath\" value=\"(.*)\">#isU", $file, $arr))
					{ $ckey = $arr[2][0]; $cretpath = $arr[3][0]; $cimg = $arr[1][0]; logger::AddRecord( "captcha fields extracted [2] ");}


                    $tfile = $CFG->datadir."/".$rnd.".gif"; $img = file_get_contents($cimg); $ff = fopen($tfile, "w+"); fwrite($ff, $img); fclose ($ff);
                    $text = "";
                    $rec_count = 4; // даём несколько попыток распознать капчу

                    while (($text == "") and ($rec_count > 0)) {
                        $text = $this->recognize($tfile, 'ca45bd941ba8006e676c27c1bc4d8eba', true);
                        $rec_count--;
                    }
                    flush();
                    @unlink($tfile);
                    $get_data = "key=".urlencode($ckey)."&retpath=".urlencode($cretpath)."&rep=$text";

                    $file = $this->getUrl("http://yandex.ru/checkcaptcha?".$get_data);

            }
            $ban_count--;

            if ($ban_count == 0 )
                throw new Exception('Превышено количество попыток!');

        } while (!$file || !$ban_count);

        return $file;
    }

    public  function getWordstat($url, $debug = false){
        global $CFG;


        $rnd = $this->rand_string(10);
        $ban_count = 4;
        $file = '';
        $ttt = false;
        if (!$this->int)
                $this->setInterface(false);
            if(file_exists($CFG->datadir."/".$this->int."_cookie.txt"))
                $ttt = file_get_contents($CFG->datadir."/".$this->int."_cookie.txt");

            if(!$ttt or  !preg_match('#fuid01#is', $ttt))
                $this->getUrl('http://kiks.yandex.ru/su/');


        logger::AddRecord(strval($this->int));


        $i = 0;
        do{
            logger::AddRecord('getWordstat запросили url '. $url);

            $file = $this->getUrl($url);

            if(preg_match('#Сервис временно недоступен#isU', $file) ):
                sleep(5);
                $file = false;
                continue;
            endif;

            $i++;

        }while(!$file && $i <3);
        if(!$file)
            throw new Exception('Wordstat нас забанил!');

        $this->referrer = $url;

        $i = 0;
        while (!preg_match ('#table class="campaign"#', $file) && $ban_count){// распознавалка капчи
            log_tofile('recognize!!!!');
            log_tofile( $this->lastHeaders);
            log_tofile($file);
            flush();

                $arr = array();
                $regexp = '#<img src="http://u.captcha.yandex.net/image\?key=(.*)">#isU';
                preg_match_all($regexp, $file, $arr);
                if (!isset($arr[1][0])){
                    sleep(1);
                    $file = $this->getUrl($url);
                    continue;
                }


                $tfile = $CFG->datadir."/queryGroups/".$rnd.".gif";
                $img   = file_get_contents('http://u.captcha.yandex.net/image?key='.$arr[1][0]);
                $ff    = fopen($tfile, "w+"); fwrite($ff, $img); fclose ($ff);

                $text = "";
                $rec_count = 4; // даём несколько попыток распознать капчу
                while (($text == "") and ($rec_count > 0)) {
                        $text = $this->recognize($tfile, 'ca45bd941ba8006e676c27c1bc4d8eba', true);
                        $rec_count--;
                }
                @unlink($tfile);
                $url2 = $url."&captcha_id={$arr[1][0]}&captcha_val=$text";

                $file = $this->getUrl($url2);

                $this->referrer = $url2;

                $ban_count--;
        }//  while (!preg_match ('#table class="campaign"#', $file) && $ban_count){// распознавалка капчи
        if ($ban_count == 0){
            throw new Exception('Превышено количество попыток!');
        }

        return $file;
    }

    /**
     * Распарсить вордстат по запросу до последней страницы.
     * Вернуть массив с запросом и частотой
     *
     * ВОЗВРАЩАЕТ ТРИМАНЫЕ ЗНАЧЕНИЯ!
     *
     * @param $query
     * @param bool $debug
     * @return array
     */
    public function getAllWordStatPages($query, $debug = false){
        $result = array();
        $query = trim($query);
        if (!$query)
            throw new Exception('Запрос не должен быть пустым!');

        $wordstatQuery = 'http://wordstat.yandex.ru/?cmd=words&page=1&text='.urlencode($query)."&geo={$this->region}&text_geo=".urlencode($this->regionText);

        $content = $this->getWordstat($wordstatQuery);
        $result  = $this->parseWordstatContent($content);
        $result[0]['query'] = $query;// тот, что парсится из текста странички, может быть обрезан по количеству символов

        while ($page = $this->getNextWordstatPage($content)):
            logger::AddRecord('поменяли интерфейс');
            $this->setInterface(false);
            logger::AddRecord('спим');
            sleep(5);
            $wordstatQuery = "http://wordstat.yandex.ru/?cmd=words&page=$page&text=".urlencode($query)."&geo={$this->region}&text_geo=".urlencode($this->regionText);
            logger::AddRecord('$this->getWordstat');
            $content = $this->getWordstat($wordstatQuery, true);
            logger::AddRecord('$this->parseWordstatContent');
            $result = array_merge($result, $this->parseWordstatContent($content, false));
        endwhile;//while ($page = getNextPage($content)){

        if (function_exists('gc_collect_cycles')) gc_collect_cycles();

        return $result;
    }//public function getAllWordStatPages($query, $debug)


    /**
     * Распасить ЛЕВУЮ табличку в вордстате.
     * вернуть массив с запросом и частотой
     *
     * ВОЗВРАЩАЕТ ТРИМАНЫЕ ЗНАЧЕНИЯ!
     *
     * @param $content string
     * @param $parseTitle bool
     * @return array
     */
    public    function parseWordstatContent($content, $parseTitle = true){
        $result = array();
        $i = 0;
        /*
         строчка Что искали со словами «hp картридж» — 181834 показа в месяц.
         */
        if ($parseTitle && preg_match("#Что искали со&nbsp;слов.*<span class='bold-style'>«(.*)»</span>&nbsp;—&nbsp;(.*)&nbsp;показ.*в&nbsp;месяц#isU", $content, $resP) ):
            $i++;
            $result[0]['query'] = trim($resP[1]);
            $result[0]['freq']  = intval($resP[2]);
        endif;

        $doc    = new DOMDocument();

        @$doc->loadHTML($content);
        $xpath = new DOMXPath($doc);// паеределать чтоли на регулярки?
        $xpathQuery = '/html/body/form/table[2]/tbody/tr/td[4]/table/tbody/tr[3]/td/table';

        $entries = $xpath->query($xpathQuery);
        foreach ($entries->item(0)->getElementsByTagName('tr')  as $key => $entry) :
            if ($key == 0) continue;
            $entry = $entry->getElementsByTagName('td');
            $result[$i]['query'] = preg_replace('#\+#is','',$entry->item(0)->nodeValue);
            $result[$i]['query'] = trim($result[$i]['query']);

            $result[$i]['freq'] = intval($entry->item(2)->nodeValue);

            $i++;
        endforeach;

        return $result;
    }//function parseContent($content){


    /**
     * получить следующую страницу в постраничке вордстата
     * Вернуть номер этой страницы, если его нет - false
     *
     * использование -  while ($page = $this->getNextWordstatPage($content))
     *
     * @param $content
     * @return bool|string
     */
    public function getNextWordstatPage($content){
        $result = false;

        $pattern = '#<div class="pages">(.*)</div>#isU';
        preg_match_all($pattern, $content, $url);

        if (isset($url[1][0])){
            $content = $url[1][0];
            $pattern = '#<a (.*)</a>#isU';
            $url2    = array();
            preg_match_all($pattern, $content, $url2);
            foreach($url2[1] as $url){
               if (preg_match('#следующая#isU', $url)){
                   $pattern = '#href=".*;page=(.*)&.*">#isU';
                   preg_match($pattern, $url, $result);
                   $result = trim($result[1]);
                   break;
               }//if (preg_match('#следующая#isU', $url)){
            }//foreach($url2[1] as $url){
        }//if (isset($url[1][0])){

        return $result;
    }

    /**
     * Получить частоту для переданного запроса в кавычках и с восклицательным знаком перед каждым словом из Вордстата
     *
     *
     * @param $query strimg
     * @param $debug boolean
     * @return int|null
     */
    public function getWordstatQueryFreq( $query, $debug  = false){
        $freq = null;
        $newQuery = preg_split('#\s+#',$query);

        $newQueryText = '';
        foreach ($newQuery as $nq):
            $newQueryText .= ' !'.$nq;
        endforeach;

        $newQueryText = trim($newQueryText);
        $newQueryText = '"'.$newQueryText.'"';


        $url = "http://wordstat.yandex.ru/?cmd=words&page=1&text=".urlencode($newQueryText)."&geo={$this->region}&text_geo=".urlencode($this->regionText);
        $content = $this->getWordstat($url, $debug);

        $res = array();

        preg_match('#&nbsp;—&nbsp;(.*)&nbsp;#isU', $content, $res);

        $freq = intval($res[1]);
        return $freq;

    }//public function getWordstatQuery($query){

     public function recognizeDirectCaptcha($content = ''){
         global $CFG;
         logger::AddRecord('recognizeDirectCaptcha');

        $result = '';
        if(!$content) throw new Exception('Нечего распознавать!');
        $image = '';
        preg_match('#<img src="(.*)" alt="captcha" />#isU', $content, $image);

        $image = $this->curlGET2($image[1]);
        $tfile = $CFG->datadir."/".$this->rand_string(10).".gif";
        file_put_contents($tfile, $image);

        $captcha = $this->recognize($tfile);

        preg_match('#<input type="hidden" name="capid" value="(.*)"/>#iU', $content, $matches);
        if(isset($matches[1]))  $post['capid'] = $matches[1];

        $geo = '';
        preg_match('#<input type="hidden" name="geo" value="(.*)">#isU', $content, $geo);
        if(isset($geo[1]))     $post['geo']     = $geo[1];

        $phrases = '';
        preg_match('#<input type="hidden" name="phrases" value="(.*)">#isU', $content, $phrases);
        if(isset($phrases[1])) $post['phrases'] = $phrases[1];

        $authredirlevel = '';
        preg_match('#<input type="hidden" name="authredirlevel" value="(.*)">#isU', $content, $authredirlevel);
        if(isset($authredirlevel[1])) $post['authredirlevel']= $authredirlevel[1];

        $cmd = '';
        preg_match('#<input type="hidden" name="cmd" value="(.*)">#isU', $content, $cmd);
      //  if(isset($matches[1]))  $post['cmd'] = $matches[1];

        preg_match('#<input type="hidden" name="ncrnd" value="(.*)">#isU', $content, $matches);
        if(isset($matches[1]))  $post['ncrnd'] = $matches[1];




        $action = '';
        preg_match('#<form action="(.*)" method="GET">#isU', $content, $action);




        $post['capcode'] = $captcha;


        $cmd = array();
        foreach ($post as $key =>&$value):
            $cmd[] = $key .'='. rawurldecode($value);
        endforeach;

        $action = $action[1].'?'.implode('&', $cmd);
        logger::AddRecord('Распознаем капчу директа с экшеном '.$action);
//http://direct.yandex.ru/registered/main.OEWgE1flE1Z3E6Vv.pl?capid=10otJ0M5uKYYseqlk0WWi4RLjuMxZuiI&capcode=590754&ncrnd=4952
//http://direct.yandex.ru/registered/main.adMMjxCP1BAmhQnH.pl?capid=10r253Etanh3rb9PJU18QEZfN2dlTFue&cmd=10r253Etanh3rb9PJU18QEZfN2dlTFue&capcode=440218
        $result = $this->curlGET2($action, $post);

        return $result;
    }

    /**
     *
     * Получить частоту запросов с кавычками и восклицательным знаком перед каждым словом из Директа
     * принимает до ста запросов.
     *
     * @throws Exception
     * @param array $query массив запросов
     * @return array
     *
     */

    public function getDirectQueryFreq(array $query){
        global $CFG;

        $oldCookieFile = $this->cookie_file;
        $this->cookie_file = $CFG->datadir."/yandexclass/direct/".$this->int."_cookie.txt";

        $result = array();
        if (!$query || empty($query)):
            throw new Exception('getDirectQueryFreq получил пустой массив запросов!');
            //debug_print_backtrace();
            //exit('1111111111111111111111');
        endif;


        $this->referrer = 'http://direct.yandex.ru/';

        $ttt = '';
        foreach($query as $q):
            if(trim($q) == '') continue;

            $tt = preg_split('#\s+#', $q);
            if(count($tt) > 7) continue;

            $t = '';
            foreach ($tt as $qq):
                $t .= '!'.$qq.' ';
            endforeach;
            $t    = trim($t);
            $ttt .= '"'.$t.'"'."\r\n";


        endforeach;
            $tryCount = 0;
            do{
                $this->setInterface(false);
                $this->cookie_file = $CFG->datadir."/yandexclass/direct/".$this->int."_cookie.txt";
                logger::AddRecord('getDirectQueryFreq попытка получить данные');
                $direct2  = $this->getDirectQueryRaw($ttt);

                $math = array();
                if ( preg_match('#Сервис временно недоступен#isU', $direct2) ):
                    logger::AddRecord('Сервис временно недоступен', 1);
                    sleep(2);
                    continue;
                endif;

                if ( preg_match('#Контрольные цифры#isU', $direct2, $math) ):

                    logger::AddRecord("интерфейс {$this->int} BANNED!!!", 100);
                    $direct2 = $this->recognizeDirectCaptcha($direct2);
                endif;

                $tryCount++;
                if($tryCount >= 15)
                    throw new Exception('getDirectQueryFreq превышенно количество попыток!');

            }while(!$direct2);

            $direct = json_decode($direct2, true);

            logger::AddRecord('getDirectQueryFreq ответ получен '.count($direct).' результатов');
            if(count($direct) == 0):
                var_dump($ttt);
                file_put_contents('direct.html', $direct2);
                var_dump($direct2);
                exit('3333333333333333333333333333333333333333333333333333333333333333333333333333');
            endif;



            if (!empty($direct['error'])):

                logger::AddRecord('Директ вернул ошибку '.$direct['error']);
                throw new Exception($direct['error']);
            endif;

        foreach($direct as $key => $d):
            $result[$key]['query']     =  trim(preg_replace('#!#s', '', $d['phrase']), '"');
            $result[$key]['freq']      =  $d['shows'];
            $result[$key]['p_clicks']  =  $d['p_clicks'];
        endforeach;

        $this->cookie_file = $oldCookieFile ;
        return $result;
    }



    /**
     *
     * Залогинится на яндекс Директе.
     * ПОСЛЕ ЛОГИНА ИНТЕРФЕЙС, ЮЗЕРАГЕНТ, КУКИ НЕ МЕНЯТЬ!
     *
     * @param $login
     * @param $passwd
     * @return string
     */
    public function loginDirect($login = '', $passwd = '') {
        global $CFG;
        $post = array();
logger::AddRecord('Зашли в loginDirect');
        if (!$login && !$passwd):
            $accounts = file($CFG->basedir.'/yandex/accounts.txt');
            list($login, $passwd) = explode(':', $accounts[mt_rand(0, count($accounts)-1)]);
        endif;

        $this->referrer = 'http://direct.yandex.ru/';
        $this->setNewUserAgent('Mozilla/5.0 (Windows; U; Windows NT 5.1; ru; rv:1.9.1.5) Gecko/20091102 MRA 5.5 (build 02842) Firefox/3.5.5');
logger::AddRecord('Проверка авторизации!');

        $content = $this->curlGET2('http://direct.yandex.ru/registered/main.pl?cmd=ForecastByWords');

        /* а не залогинены ли мы уже? */
        if (!preg_match('#запомнить меня#isU', $content)):
            return $content;
        endif;
logger::AddRecord('Нет Авторизации!');
        echo '<br><br><br><br><br><br><br><br>';
                   var_dump($this->int);
                   var_dump($this->lastHeaders);
                   var_dump($this->cookie_file);
                   var_dump($content);
	    //$content = $this->curlGET2('http://passport.yandex.ru/passport?mode=auth&retpath=http%3A%2F%2Fdirect.yandex.ru%2Fregistered%2Fmain.pl%3Fcmd%3DForecastByWords%26authredirlevel%3D1259925463.0');
        $retpath = 'http://direct.yandex.ru/';
        preg_match('#<input type="hidden" name="retpath" value="(.*)"#isU', $content, $matches);
        $retpath = $matches[1];
        if (!isset($matches[1])):
            var_dump($content);
            exit;
        endif;

        preg_match('#input type="hidden" name="idkey" value="(.*)"#isU', $content, $matches);
        $idkey = $matches[1];

        preg_match('#<input type="hidden" name="display" value="(.*)" />"#isU', $content, $matches);
        if(isset($matches[1]))  $post['display'] = $matches[1];

        preg_match('#<input type="hidden" name="from" value="(.*)" /> "#isU', $content, $matches);
        if(isset($matches[1]))  $post['from'] = $matches[1];



	    $post = array(
            'retpath'   => $retpath,
            'idkey'     => $idkey,
            'timestamp' => '',
            'login'     => $login,
            'passwd'    => $passwd,
            'In'        =>'Войти',
            'twoweeks'  => 'yes',
        );
        logger::AddRecord("Авторизируемся на яндексе с login:$login ");
		$content2 = $this->curlPost('http://passport.yandex.ru/passport?mode=auth', $post);
        if(preg_match('#Контрольные цифры#isU', $content2, $matches)):
            logger::AddRecord('Яндекс вернул капчу');
            $content2 = $this->recognizeDirectCaptcha($content2);
        endif;

    if(preg_match('#Оценка месячного бюджета рекламной кампании#is',$content2)):
        logger::AddRecord('Успешно авторизовались');
    else:
        logger::AddRecord('Не Авторизовались!!!!');
        var_dump($content2);
        exit;
    endif;
        return $content2;

    }//public function login($login, $passwd) {


    /**
     * получить данные из директа для  переданного запроса.
     * возвращает json строку.
     *
     *
     * @param $query string
     * @return string|boolean
     */
    public function getDirectQueryRaw($query){
        global $CFG;
        logger::AddRecord('Зашли в getDirectQueryRaw');
        $this->referrer = 'http://direct.yandex.ru/';
        $tt = $this->loginDirect();
        $hh = $this->lastHeaders;
        $ii = $this->int;
        $cc = $this->cookie_file;
        $cookicontent = file_get_contents($this->cookie_file);
/*
        var_dump($tt);
var_dump($this->cookie_file);

var_dump($this->lastHeaders);
echo '<br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>';
*/
        logger::AddRecord('Вышли из авторизации, для проверки дергаем яндекс');
        $content = $this->curlGET2('http://kiks.yandex.ru/fu/');
        $content = $this->curlGET2('http://direct.yandex.ru/registered/main.pl?cmd=ForecastByWords');
        $cookicontent2 = file_get_contents($this->cookie_file);
/*

        var_dump('111');
        var_dump($content);
var_dump($this->cookie_file);

var_dump($this->lastHeaders);
        echo '<br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>';
flush();
*/

        if(preg_match('#Чтобы дать объявление, войдите под своим именем на Яндексе#isU', $content, $matches)):
            logger::AddRecord('Проверочное дерганье яндекса не удалось :(');
            echo '<br><br><br><br><br><br><br><br>';
            logger::AddRecord('Контент который вернул loginDirect() ');
            var_dump($tt);
            echo '<br><br><br><br><br><br><br>';
            logger::AddRecord('Контент который вернуло дергание');
            var_dump($content);
            echo '<br><br><br><br><br><br><br>';
            logger::AddRecord('Заголовки ПЕРЕД ПРОВЕРОЧНЫМ ДЕРГАНЬЕМ');
            var_dump($hh);
            echo '<br><br>';
            logger::AddRecord('Имя файла кукисов ДО '.$cc);
            logger::AddRecord('Имя файла кукисов ПОСЛЕ '.$this->cookie_file);


            logger::AddRecord('Интерфейс ДО '.$ii);
            logger::AddRecord('Интерфейс ПОСЛЕ '.$this->int);
            echo '<br>';


            logger::AddRecord('Файл кукисов ДО ');
            echo '<br>';
            var_dump($cookicontent);
            echo '<br>';
            logger::AddRecord('Файл кукисов ПОСЛЕ');
            echo '<br>';
            var_dump($cookicontent2);

            echo '<br><br><br><br><br>';
            logger::AddRecord('Заголовки ПЕРЕД ПРОВЕРОЧНЫМ ДЕРГАНЬЕМ');
            var_dump($hh);
            echo '<br><br><br>';
            logger::AddRecord('Заголовки ПОСЛЕ ДЕРГАНЬЯ!');
            var_dump($this->lastHeaders);



            echo '<br>-------------------------------------------<br>';
        exit;
        else:
            logger::AddRecord('Проверка прошла успешно, идем дальше');
        endif;

        if(preg_match('#Контрольные цифры#isU', $content, $matches)):
            $content = $this->recognizeDirectCaptcha($content);
        endif;

        preg_match('#http://direct\.yandex\.ru/registered/main\.(.*)\.pl#isU', $content, $matches);
        if (isset($matches[1])):
            $key = trim($matches[1]);
        else:
            var_dump($content);
            flush();
            throw new Exception(' НЕ залогинились в директ!');
        endif;

        $url = 'http://direct.yandex.ru/registered/main.'.$key.'.pl?csrf_token='.$key;

        $post = array(
             'ajax' => 1,
             'cmd'  => 'calcForecast',
             'geo'  => $this->region,
             'no_auto_catalog'=> 1,
             'phrases'        => $query,
        );



        logger::AddRecord('POST запрос на яндекс для получения даных '.$url);

        $result = $this->curlPost($url, $post);
        logger::AddRecord('получили ответ');


        $this->referrer = $url;
        return $result;
    }//public function getDirectQueryRaw($query){



    /**
     *
     * Установить регион для парсилок. По умолчанию Москва (без области).
     *
     * @throws Exception
     * @param $region_id
     * @param $region_text
     * @return void
     */
    public  function setRegion($region_id, $region_text){

        if (!is_numeric($region_id))
            throw new Exception('Неверный region_id');

        $region_id   = (int) $region_id;
        $region_text = trim($region_text);

        if (!$region_text)
            throw new Exception('Неверный region_text');


        $this->region     = $region_id;
        $this->regionText = $region_text;

    }//public  function setRegion($region_id, $region_text){



    public  function getSitePosition($query, $site, $postion = 50){

        $site =  parse_url($site);
        if(isset($site['host']))
            $site = preg_replace('#www\.#', '', $site['host']);
        else
            $site = preg_replace('#www\.#', '', $site['path']);

        $data = $this->getParsed($query, $postion);
        $site = str_replace('.', '\.', $site);

        foreach ($data as $pos => $d):
            if (preg_match("#$site#isU", $d['page']))
                return $pos +1 ;
        endforeach;

        return false;

    }




    function __destruct(){

        parent::__destruct();
    }






    /*************************************************************/
    public function loginDirect2($login = '',$passwd = ''){
        global $CFG;
        logger::AddRecord('Зашли в loginDirect2');
        if (!$login && !$passwd):
            $accounts = file($CFG->basedir.'/yandex/accounts.txt');
            list($login, $passwd) = explode(':', $accounts[mt_rand(0, count($accounts)-1)]);
        endif;



        $html = $this->curlGET2('http://passport.yandex.ru/passport?mode=auth&retpath=http%3A%2F%2Fdirect.yandex.ru%2Fregistered%2Fmain.pl%3Fcmd%3DForecastByWords%26authredirlevel%3D1282898639.0');

        preg_match("/name=\"retpath\"([^>]+)value=\"([^\"]+)\"/", $html, $matches);
        if(!isset($matches[2])):
            $post['retpath'] = 'http://direct.yandex.ru';
        else:
            $post['retpath'] =  trim($matches[2]);
        endif;



        preg_match("/name=\"idkey\"(.*)value=\"([^\"]+)\"/", $html, $matches);
        if(isset($matches[2])):
            $post['idkey'] = trim($matches[2]);
        endif;

        $post = array(
                        'timestamp'  => '',
                        'login'      => $login,
                        'passwd'     => $passwd,
                        'In'         => 'Войти',
                        'twoweeks'   => 'yes',
        );

        $html = $this->curlPost('http://passport.yandex.ru/passport?mode=auth', $post);
        if(preg_match('#запомнить меня#is',$html))
            throw new Exception('Не залогинились в директ!');

        return $html;

    }//public function loginDirect(){

    public function getDirectRaw2(array $queries, $html = ''){

            if(!$html)
                $html = $this->curlGET2('http://direct.yandex.ru/registered/main.pl?cmd=ForecastByWords');

            if(preg_match('#запомнить меня#is',$html))
                throw new Exception('Не залогинились в директ!');

            if ( preg_match('#Контрольные цифры#isU', $html, $math) ):
                    $html = $this->recognizeDirectCaptcha($html);
            endif;


            preg_match_all('#Location:\s+([^\n]+)#i', $html, $matches);
            if (isset($matches[1][0])):
                var_dump($html);
                var_dump($this->url);
                throw new Exception('http://direct.yandex.ru/registered/main.pl?cmd=ForecastByWords вернул редирект!');
            endif;

            preg_match('#http://direct\.yandex\.ru/registered/main\.([^\.]+)\.pl#', $html, $matches);
            if (isset($matches[1])):
            		$idkey = $matches[1];
            else:
                var_dump($this->url);
                var_dump($html);

                throw new Exception('Не смогли найти ключ main.pl!');
            endif;


            $post = array(
                'ajax' => 1,
                'cmd'  => 'calcForecast',
                'geo'  =>  $this->region,
                'no_auto_catalog' => 1,
                'phrases'         => implode("\n", $queries),
            );
            $result  = $this->curlPost('http://direct.yandex.ru/registered/main.'.$idkey.'.pl?csrf_token='.$idkey, $post );
            $direct  = json_decode($result, true);

            if (!empty($direct['error'])):
                var_dump($queries);
                logger::AddRecord('Директ вернул ошибку '.$direct['error']);
                throw new Exception($direct['error']);
            endif;

            if(!count($direct)):
                var_dump($this->url);
                var_dump($result);
                throw new Exception('Пост запрос в директ вернул не json!');
            endif;
            return $direct;
        }//public function getDirectRaw(){


        public function getDirectQueryFreq2(array $queries){
            $this->setInterface(false);
            logger::AddRecord('Начинаем работу через интерфейс'.$this->int);
            $html  = $this->loginDirect2();
            if(preg_match('#<h1>Персональные данные</h1>#',$html))
                $html = '';


            $quoted_queries = array();

            foreach($queries as $q):
                if(trim($q) == '') continue;

                $tt = preg_split('#\s+#', $q);
                if(count($tt) > 7) continue;

                $t = '';
                foreach ($tt as $qq):
                    $t .= '!'.$qq.' ';
                endforeach;
                $t    = trim($t);
                $quoted_queries[] = '"'.$t.'"'."\r\n";
            endforeach;//foreach($queries as $q):


            foreach ($this->getDirectRaw2($quoted_queries, $html) as $key => $value):
                $result[$key]['query']     =  trim(preg_replace('#!#s', '', $value['phrase']), '"');
                $result[$key]['freq']      =  $value['shows'];
                $result[$key]['p_clicks']  =  $value['p_clicks'];
            endforeach;
            return $result;
        }//public function getDirectQueryFreq2(array $queries){

}//class yandex  extends curl

