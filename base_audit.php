<?php
require_once ("logger.class.php");

set_time_limit(60);
setlocale(LC_ALL, "ru_RU.UTF-8");


/**
 * Created by JetBrains PhpStorm.
 * User: dlitvin
 * Date: 01.09.11
 * Time: 14:13
 * To change this template use File | Settings | File Templates.
 */
require_once('curl.class.php');
require_once('yandex.class.php');
require_once('rambler.class.php');
require_once('parser.class.php');
require_once('google.php');
require_once('idna.class.php');

class base_audit {

    private $curl    = null;
    private $ya      = null;
    private $gooo    = null;
    private $rambler = null;
    private $parser  = null;


    public $site;

    public  function setSite($site){
        $site = parse_url(trim($site));

        if(isset($site['host']))
            $site = preg_replace('#www\.#', '', $site['host']);
        else
            $site = preg_replace('#www\.#', '', $site['path']);

        $this->site = $site;
    }

    public  function __construct($site){
        $this->setSite($site);


        $this->curl = new curl2();
        $this->ya   = new yandex();
        $this->gooo = new google();
        $this->rambler = new rambler();
        $this->idna = new idna_convert();

        date_default_timezone_set('Europe/Moscow');
    }
    /*
    *
    *    1.	Кол-во страниц в индексе поисковых систем
    *   site.ru – это домен анализируемого сайта.
    *    Яндекс
    *    В поисковой строке на странице http://ya.ru/ вводим:
    *    host:www.site.ru | host:site.ru
    *    и получаем общее кол-во страниц данного хоста. Оно будет выведено под логотипом Яндекса в левом верхнем углу.
    *    Внимание, в результатах используется буквенные значения (млн, тыс) их, необходимо переводить в числовые значения.
    *    Рамблер
    *    На странице http://nova.rambler.ru/srch/advanced в строке «Сайты» вводим анализируемый домен (без www).
    *    И в результатах под строкой поиска будет выведено кол-во страниц, так же используются буквенные значения порядков, их необходимо переводить в числовые значения.
    *    Google
    *    На странице http://google.com в строке поиска вводим:  site:site.ru
    *    анализируемый домен (без www). И в результатах под строкой поиска будет выведено кол-во страниц
    */
    public function GetPageInIndexes(){

        $result = array();

        $result['yandex'] = $this->getYaPageIndex();
        $result['google'] = $this->getGooglePageIndex();
        //$result['rambler'] = $this->getRamblerPageIndex();
        $result['rambler'] = $result['yandex'];
        return $result;
    }


    public  function getYaPageIndex(){
        $ya_str = 'http://yandex.ru/yandsearch?text=host%3A'.$this->site.'+|+host%3Awww.'.$this->site.'&lr=213';
        $this->curl->setUrl($ya_str);
        $content  = $this->ya->getRaw($ya_str);

        preg_match_all('#<strong class="b-head-logo__text">(.*)</strong>#isU', $content, $res);
        preg_match('#\d+#',$res[1][0], $d);
        $d = (int) $d[0];
        if (preg_match('#тыс#',$res[0][0])){
            $d = $d*1000;
        }
        if (preg_match('#млн#',$res[0][0])){
            $d = $d*1000000;
        }
        return $d;
    }

    public function getGooglePageIndex(){
        $res = array();
        /* гугл */
       $gooo_str = 'http://www.google.ru/search?hl=ru&source=hp&biw=1298&bih=303&q=site%3A'.$this->site.'&oq=site%3A'.$this->site.'&aq=f&aqi=&aql=&gs_sm=e&gs_upl=1693l5916l0l6157l13l12l0l6l0l0l282l1480l0.1.5l6l0';
        $i = 0;
        do{//зело криво работает

         //$content = $this->gooo->getContent($gooo_str);
         $content = $this->curl->getUrl($gooo_str);
         // $content = '<div><div id=resultStats>Результатов: примерно 2&nbsp;080&nbsp;000<nobr>  (0,04 сек.)&nbsp;</nobr></div></div>';
            if (strlen($content) < 100) continue;
                //<div>Результатов: примерно 1&nbsp;700&nbsp;000</div>
                //<div id="resultStats">Результатов: примерно 1&nbsp;690&nbsp;000<nobr>  (0,09 сек.)&nbsp;</nobr></div>
                //<font size="-1">Результаты <b>1</b> - <b>10</b> из примерно <b>1,700,000</b> для <b>site:lenta.ru</b>. (<b>0.04</b> сек.)</font>
            preg_match_all('#<div>Результатов:.*примерно(.*)</div>#isU', $content, $res);


            if (!isset($res[1][0]) || !$res[1][0]):
                $pattern = iconv('utf-8', 'windows-1251', '#<div>Результатов:.*примерно(.*)</div>#isU' );
                preg_match_all($pattern, $content, $res);
            endif;

            if (!isset($res[1][0]) || !$res[1][0]){
                 preg_match_all('#<div id=.?resultStats.?>(.*)<nobr>#iU', $content, $res);
            }

            if (!isset($res[1][0]) || !$res[1][0]){
                 preg_match_all('#<font size="-1">Результаты <b>1</b> - <b>\d+</b> из примерно <b>(.*)</b> для <b>#isUu', $content, $res);
            }

            if (!isset($res[1][0]) || !$res[1][0]){
                echo($content);
                echo 'регулярки закосячили';
                exit('111');
            }

            $i++;

            if ($i == 10 )
                throw new Exception('НЕ распознали google!');
        }while(!isset($res[1][0]));

        $result = (int) preg_replace('#\D#','',$res[1][0]);
        return $result;

    }

    public function getRamblerPageIndex(){
        /* рэмблер */
        $rambler_str = 'http://nova.rambler.ru/search?btnG=%D0%9D%D0%B0%D0%B9%D1%82%D0%B8!&amp;query='.$this->site;
        $content = $this->rambler->query($rambler_str );
        preg_match_all('#<div class="info">(.*)</div>#isU', $content, $res);
        if(!isset($res[1][0])){
            var_dump($content);
            var_dump($res);
            exit;
        }
        preg_match('#\d+#',$res[1][0], $d);
        $d = (int) $d[0];
        if (preg_match('#тыс#',$res[1][0])){
            $d = $d*1000;
        }
        if (preg_match('#млн#',$res[1][0])){
            $d = $d*1000000;
        }
        return $d;
    }


    private function getMonth($strTimer, $strDate){
        $date =  preg_split('#[.]#', $strDate );
        $strTimer =  preg_split('#[.]#', $strTimer );

        $month = $date[0] - $strTimer[0];
        $month--;
        $month= $month * 12;// количество лет * 12
        $month += 12 - intval($strTimer[1]); // добрать месяцев с того года
        $month += (int)$date[1];// добрать месяцев из этого года

        return $month;
    }


    private  function GetDomenAgeByPP($site){
        $str = 'http://whois.pp.ru/';

        $oldInt = $this->curl->int;

        $post = array(
            'domain'=> $site
        );

        do{
                sleep(5);
                $this->curl->setInterface(false);
                $this->curl->setNewUserAgent();
                $content = $this->curl->curlPost($str, $post);
                if (preg_match('#Name or service not known#isU', $content))
                    return false;

        }while (
            preg_match('#The page you are looking for is temporarily unavailable#isU',$content)
            ||
            preg_match('#Нет соединения#isU',$content)
            ||
            preg_match('#Please try to connect later#isU',$content)
            ||
            !$content
        );

        $this->curl->setInterface($oldInt);

        preg_match_all("#Creation Date</span>:(.*)<span#isU", $content, $res);

        if (!isset($res[1][0]))
            preg_match_all('#created</span>:(.*)<span#isU', $content, $res);
        if (!isset($res[1][0]))
            preg_match_all('#Created On:(.*)UTC#isU', $content, $res);
        if (!isset($res[1][0]))
            preg_match_all('#Domain Registration Date:(.*)Domain#isU', $content, $res);

        if (!isset($res[1][0])):
            file_put_contents('/home/texttool/domains/texttool.antarion.ru/public_html/logs/'.$this->ya->rand_string().'!!!!!!!!!!!!!!.txt', $content);
            flush();
            return false;
        endif;
        return $res;
    }

    private function ae_whois($query){



        $query = mb_convert_case($query, MB_CASE_LOWER, "UTF-8");
        $this->curl->setInterface(false);
        $this->curl->setNewUserAgent();

        $whoisservers = array(

        //"com"=>"whois.crsnic.net",
        "com"=>"whois.verisign-grs.com",
        "net"=>"whois.verisign-grs.com",
        "edu"=>"whois.educause.net",
        "org"=>"whois.publicinterestregistry.net",
        "arpa"=>"whois.arin.net",
        "ripe"=>"whois.ripe.net",
        "mil"=>"whois.nic.mil",
        "coop"=>"whois.nic.coop",
        "museum"=>"whois.museum",
        "biz"=>"whois.neulevel.biz",
        "info"=>"whois.afilias.net",
        "name"=>"whois.nic.name",
        "gov"=>"whois.nic.gov",
        "aero"=>"WHOIS.AEROREGISTRY.NET",
        "ns"=>"whois.internic.net",
        "ip"=>"whois.ripe.net",
        "ad"=>"whois.ripe.net",
        "al"=>"whois.ripe.net",
        "am"=>"whois.amnic.net",
        "as"=>"whois.gdns.net",
        "at"=>"whois.nic.at",
        "au"=>"whois.audns.net.au",
        "az"=>"whois.ripe.net",
        "ba"=>"whois.ripe.net",
        "be"=>"whois.dns.be",
        "bg"=>"whois.ripe.net",
        "br"=>"whois.nic.br",
        "by"=>"whois.ripe.net",
        "ca"=>"whois.cira.ca",
        "cc"=>"whois.nic.cc",
        "ch"=>"whois.nic.ch",
        "ck"=>"whois.ck-nic.org.ck",
        "cl"=>"nic.cl",
        "cn"=>"whois.cnnic.net.cn",
        "cx"=>"whois.nic.cx",
        "cy"=>"whois.ripe.net",
        "cz"=>"whois.nic.cz",
        "de"=>"whois.denic.de",
        "dk"=>"whois.dk-hostmaster.dk",
        "do"=>"ns.nic.do",
        "dz"=>"whois.ripe.net",
        "ee"=>"whois.tld.ee",
        "ec"=>"whois.nic.ec",
        "eg"=>"whois.ripe.net",
        "es"=>"whois.ripe.net",
        "fi"=>"whois.ripe.net",
        "fo"=>"whois.ripe.net",
        "fr"=>"whois.nic.fr",
        "ga"=>"whois.ripe.net",
        "gb"=>"whois.ripe.net",
        "ge"=>"whois.ripe.net",
        "gl"=>"whois.ripe.net",
        "gm"=>"whois.ripe.net",
        //"gr"=>"estia.ics.forth.gr",
        "gr"=>"whois.ripe.net",
        "gs"=>"whois.adamsnames.tc",
        "hk"=>"whois.hkdnr.net.hk",
        "hr"=>"whois.ripe.net",
        "hu"=>"whois.nic.hu",
        "id"=>"muara.idnic.net.id",
        "ie"=>"whois.domainregistry.ie",
        "il"=>"whois.isoc.org.il",
        "in"=>"whois.inregistry.net",
        "is"=>"horus.isnic.is",
        "it"=>"whois.nic.it",
        "jo"=>"whois.ripe.net",
        "jp"=>"whois.nic.ad.jp",
        "kg"=>"whois.domain.kg",
        "kh"=>"whois.nic.net.kh",
        "kr"=>"whois.krnic.net",
        "kz"=>"whois.nic.kz",
        "la"=>"whois.nic.la",
        "li"=>"domex.switch.ch",
        "lk"=>"arisen.nic.lk",
        "lt"=>"whois.domreg.lt",
        "lu"=>"whois.dns.lu",
        "lv"=>"whois.nic.lv",
        "me"=>"whois.nic.me",
        "ma"=>"whois.ripe.net",
        "mc"=>"whois.ripe.net",
        "md"=>"whois.ripe.net",
        "mm"=>"whois.nic.mm",
        "ms"=>"whois.adamsnames.tc",
        "mt"=>"whois.ripe.net",
        "mx"=>"whois.nic.mx",
        "nl"=>"whois.domain-registry.nl",
        "no"=>"ask.norid.no",
        "nu"=>"whois.worldnames.net",
        "nz"=>"whois.srs.net.nz",
        "pl"=>"whois.dns.pl",
        "pro"=>"whois.registrypro.pro",
        "pt"=>"whois.ripe.net",
        "ro"=>"whois.rotld.ro",
        "ru"=>"whois.ripn.net",
        "se"=>"whois.iis.se",
        "sg"=>"qs.nic.net.sg",
        "sh"=>"whois.nic.sh",
        "si"=>"whois.arnes.si",
        "sk"=>"whois.ripe.net",
        "sm"=>"whois.ripe.net",
        "st"=>"whois.nic.st",
        "su"=>"whois.ripn.net",
        "tc"=>"whois.adamsnames.tc",
        "tf"=>"whois.adamsnames.tc",
        "th"=>"whois.thnic.net",
        "tj"=>"whois.nic.tj",
        "tn"=>"whois.ripe.net",
        "to"=>"whois.tonic.to",
        "tr"=>"whois.ripe.net",
        "tw"=>"whois.twnic.net",
        "tv"=>"whois.nic.tv",
        "tel"=>"whois.nic.tel",
        "ua"=>"whois.net.ua",
        "uk"=>"whois.nic.uk",
        "us"=>"whois.nic.us",
        "va"=>"whois.ripe.net",
        "vg"=>"whois.adamsnames.tc",
        "ws"=>"whois.worldsite.ws",
        "yu"=>"whois.ripe.net",
        "za"=>"apies.frd.ac.za",
        "xn--p1ag"=>"ru.whois.i-dns.net",
        "xn--p1ag"=>"ru.whois.i-dns.net",
        "xn--j1ae"=>"whois.i-dns.net",
        "xn--e1ap"=>"whois.i-dns.net",
        "xn--c1av"=>"whois.i-dns.net",
        "net.ru"=>"whois.ripn.net",
        "org.ru"=>"whois.ripn.net",
        "pp.ru"=>"whois.ripn.net",
        "spb.ru"=>"whois.relcom.ru",
        "msk.ru"=>"whois.relcom.ru",
        "ru.net"=>"whois.relcom.ru",
        "yes.ru"=>"whois.regtime.net",
        "uk.com"=>"whois.centralnic.com",
        "uk.net"=>"whois.centralnic.com",
        "gb.com"=>"whois.centralnic.com",
        "gb.net"=>"whois.centralnic.com",
        "eu.com"=>"whois.centralnic.com",
        'xn--p1ai' => 'WHOIS.TCINET.RU',
        'рф' => 'WHOIS.TCINET.RU',
        'ua' => 'WHOIS.UA',
        'me' => 'whois.nic.me',
        'uz' => 'whois.cctld.uz',
        'mobi' => 'whois.dotmobiregistry.net',

        );
        if (preg_match('#\.com\.ua#isU', $query)):
            $zone = 'com.ua';
        elseif (preg_match('#\.net\.ua#isU', $query)):
            $zone = 'net.ua';
        else:
            $zone = explode('.', $query); $zone = $zone[1];
        endif;



        if (!array_key_exists($zone,$whoisservers))
            return 0;
        if(in_array($zone, array('ru'))):
           $servers[1] =  'WHOIS.TCINET.RU';
           $servers[2] =  'WHOIS.NIC.RU';
           $servers[3] =  'whois.ripn.net';
           $servers[4] =  'whois.reg.ru';
            $tt = rand(1, 4);
            $servers[] = $servers[$tt];
        else:
            $servers[] = $whoisservers[$zone];
        endif;




        if(preg_match('#рф#is', $query))
            $query = $this->idna->encode($query);
        if(preg_match('#com$#is', $query))
               $query =  '='.$query;

        $response = '';


        foreach ($servers as $server):
            /*
            $f = stream_socket_client("tcp://$server:43", $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $socket_context);

            if (!$f)
                throw new Exception($ae_whois_errno . $ae_whois_errstr);

            fwrite($f, $query."\r\n");
            $response = '';

            while (!feof($f)):
                $response .= fgets($f, 1024);
            endwhile;
            */
            //$sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
            if (false == ($sock = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP))) {
                   throw new Exception( "socket_create() failed: reason: " . socket_strerror(socket_last_error()) . "\n");
            }

            if (false == (@socket_bind($sock, $this->curl->int))) {
                throw new Exception( "socket_bind() failed: reason: " . socket_strerror(socket_last_error($sock)) . "\n");
            }

            if (false == (@socket_connect($sock, $server, 43))) {
                throw new Exception( "socket_connect() failed: reason: " . socket_strerror(socket_last_error($sock)) . "\n");
            }


            socket_write($sock, $query."\r\n");
            $response = '';
            $whois_answer = '';
            do{
                 if (false === ($whois_answer = socket_read($sock, 16384, PHP_BINARY_READ))) {
                   throw new Exception( "socket_read() failed: reason: " . socket_strerror(socket_last_error()) . "\n");
                 }
                $response    .= $whois_answer;

            }while($whois_answer !== '');

            if(
                !preg_match('#No entries found for the selected source#isU', $response)
                &&
                !preg_match('#No match for#isU', $response)
                &&
                !preg_match('#Not found#isU', $response)
                  &&
                !preg_match('#database is down#isU', $response)
                &&
                !preg_match('#Please wait a while and try again#isU', $response)
                &&
                !preg_match('#Unknown query format#isU', $response)

            ) break;
                else var_dump($query);


            /******************************************/
            socket_close ($sock); unset($sock);
        endforeach;//foreach ($servers as $server):


        preg_match_all("#Creation Date:(.*)Expiration#isU", $response, $res);
        if (!isset($res[1][0]))
            preg_match_all('#created:(.*)paid-till#isU', $response, $res);
        if (!isset($res[1][0]))
            preg_match_all('#Created On:(.*)UTC#isU', $response, $res);
        if (!isset($res[1][0]))
            preg_match_all('#Domain Registration Date:(.*)Domain#isU', $response, $res);

        if (!isset($res[1][0]))
            preg_match_all('#created:.*0-UANIC (.*)changed#isU', $response, $res);
        if (!isset($res[1][0]))
            preg_match_all('#registered:(.*)changed#isU', $response, $res);

        if (!isset($res[1][0]))
             preg_match_all('#Domain created:(.*)\(GMT.*#isU', $response, $res);

        if (!isset($res[1][0]))
            preg_match_all('#Domain Created:(.*)Domain Last Updated#isU', $response, $res);

        if (!isset($res[1][0]))
            preg_match_all('#created:(.*)modified#sU', $response, $res);

        if (!isset($res[1][0]))
            preg_match_all('#Created:(.*)Last Update:#isU', $response, $res);
        if (!isset($res[1][0]))
            preg_match_all('#Registered:(.*)%#isU', $response, $res);
        if (!isset($res[1][0]))
            preg_match_all('#Registration Date:(.*)Expiration Date#isU', $response, $res);
        if (!isset($res[1][0]))
            preg_match_all('#Domain Name Commencement Date:(.*)Expiry Date#isU', $response, $res);




        if (!isset($res[1][0]))
            preg_match_all('#Domain Create Date:(.*)Domain Last Updated Date#isU', $response, $res);
        if (!isset($res[1][0]))
            preg_match_all('#Changed:(.*)\[Holder\]#isU', $response, $res);

        $this->ae_content = $response;

    return trim($res[1][0]);
}//private function ae_whois($query)

    /**
     *
     * 2.	Возраст домена
     *   В адресной строке вводим следующий урл:
     *   https://www.nic.ru/whois/?domain=site.ru
     *   получаем значение в графе «created:» и переводим его в числовое значение в месяцах.
     *
     * @return int
     *
     */
    public  function GetDomenAge($site = false){
        global $CFG;
        $oldLog = logger::getLogFileName();
        logger::setLogFileName('GetDomenAge');

        if($site)
            $this->site = $site;
        if(!$site && !$this->site)
            throw new Exception('НЕ указан сайт!');


        $site = mysql_real_escape_string($this->site);
        $sql = "SELECT * FROM `site_ages` WHERE `site` = '$site' ";

        $age = mysql_fetch_assoc(db_query($sql));
        if ($age){
            return $this->getMonth(date("Y.m.d",$age['age']),date("Y.m.d") );
        }

        /*********************************************/

        $site = explode('.', $this->site);

        if(count($site) == 3)
            $site = $site[1].'.'.$site[2];
        else
            $site = $this->site;



       // $res = $this->GetDomenAgeByPP($site);
        $res = $this->ae_whois($site);

        $ttt = preg_replace('#&nbsp;#', '', $res);
        $strTimer = trim($ttt);
        $strTimer2 = preg_split('#\.#', $strTimer);
        if(!isset($strTimer2[1])){
            $time  = strtotime($ttt);
            $strTimer = date("Y.m.d", $time);

            $strTimer2 = preg_split('#\.#', $strTimer);

        }
        $timestamp = mktime(0,0,0,intval($strTimer2[1]),intval($strTimer2[2]),intval($strTimer2[0]));

        if($timestamp < 0 ):
            logger::AddRecord($strTimer2);
        endif;
        if($timestamp > 0 ):
            $site = mysql_real_escape_string($this->site);
            $sql = "INSERT INTO `site_ages` (`site`,`age`) VALUES ('$site', $timestamp)";
            db_query($sql);
        endif;

        $result  = $this->getMonth($strTimer ,date("Y.m.d") );
        if (($result < 0)|| ($result > 500) ):
                $message = $site."\r\n\r\n\r\n\r\n\r\n\r\n\r\n\r\n".$this->ae_content."\r\n\r\n".$this->curl->int;
                $header_ = 'MIME-Version: 1.0' . "\r\n" . 'Content-type: text/plain; charset=windows-1251' . "\r\n";
                mail('v.kuznetsov@antarion.ru', 'Ошибка даты',$message , $header_);


            $result = 0;
        endif;

        logger::WriteToLog();
        logger::setLogFileName($oldLog);

        return $result;


    }
    /**
     *
     * 3.	тИЦ сайта
     *   В адресной строке вводим следующий урл:
     *   http://bar-navig.yandex.ru/u?ver=2&show=32&url=http://site.ru
     *   получаем значение “value” параметра “tcy”.
     *
     * @return int
     */
    public  function GetDomenTic(){
        $str = 'http://bar-navig.yandex.ru/u?ver=2&show=32&url=http://'.$this->site;
        $content = $this->curl->getUrl($str);

        preg_match_all('#<tcy rang="\d+" value="(.*)"/>#isU', $content, $tic);

        return (int) $tic[1][0];
    }


    /**
     * 4.	PR сайта
     * @return int|string
     *
     */
    public  function GetDomenPR(){
        return $this->gooo->GetPr($this->site);
    }

    /**
     * 5.	Входящих ссылок по Yahoo
     * @return int
     */
    public function Inlinks(){
        $result = 0;
        $str = 'http://siteexplorer.search.yahoo.com/uk/search?p='.$this->site.'&bwm=i&bwmo=d&bwmf=s';
        $content = $this->curl->getUrl($str);
        //<span class="btn">Inlinks (7,467)</span>
        preg_match_all('#<span class="btn">Inlinks ((.*))</span>#isU', $content, $res);
        $result = (int) preg_replace('#\D#','',$res[1][0]);

        return $result;
    }


    /**
     * 6.	Исходящие ссылки Bing
     *   В адресной строке вводим следующий урл (site.ru – без www, допустимы домены только второго уровня):
     *   http://www.bing.com/search?q=LinkFromDomain%3Asite.ru&go=&form=QBRE&filt=all
     *   В строке Результаты: 1 — 10 из X, получаем значение X. Если введён домен ниже второго уровня – то, результат выводится в виде – «Для доменов выше второго уровня – не известно».
     */
    public function Outlinks(){
        $result = 0;
        $gooo_str = 'http://www.bing.com/search?q=LinkFromDomain%3A'.$this->site.'&go=&form=QBRE&filt=all';
        $content = $this->curl->getUrl($gooo_str);

        preg_match_all('#<span class="sb_count" id="count">Результаты:.*из(.*)</span>#isU', $content, $res);
        if(!isset($res[1][0])):
            preg_match_all('#<span class="sb_count" id="count">.*of(.*)results</span>#isU', $content, $res);
        endif;

        if(!isset($res[1][0])):
            var_dump($content);
        endif;
        $result = (int) str_ireplace('&#160;','', $res[1][0]);

        return $result;
    }

    /**
     * 7.	Присутствие в основных каталогах
     *
     * @return array
     */
    public function GetCatalogs(){
        /*
        Яндекс.Каталог
        в адресной строке вводим: http://search.yaca.yandex.ru/yca/cy/ch/site.ru/ (домен без www)
        если выводится сообщение «ресурс не описан в Яндекс.Каталоге», то сохраняем значение, отсутствия в каталоге,
        в противном случае, сохраняем значение присутствия в каталоге и рубрику, в которой он найден (тег <h1>).
        */
        $result = array();
        $res    = array();
        $str = 'http://yaca.yandex.ru/yca/cy/ch/'.$this->site.'/';
        $this->curl->setFollowLocation(1);
        $content = $this->curl->curlGET2($str);
        //<div class="path2root"><a href="http://yaca.yandex.ru/yca/cat/">Каталог</a> /<a href="http://yaca.yandex.ru/yca/cat/Media/">СМИ</a> /<h1><a href="http://yaca.yandex.ru/yca/cat/Media/Other/">Прочее</a></h1></div>
        preg_match_all('#<h1 class="b-rubric__title"><a href=".*" class="b-path__link">(.*)</a></h1>#isU', $content, $res);

        if (isset($res[1][0]) && $res[1][0]):
            $result['yandex'] = $res[1][0];
        else:
            $result['yandex'] = 'Отсутствует';
        endif;

        /**
        DMOZ
        в адресной строке вводим: http://www.dmoz.org/search?q=site.ru (домен без www)
        если в результатах выводится список "Open Directory Categories", значит, сохраняем значения присутствия в каталоге.
         */

        $str = 'http://www.dmoz.org/search?q='.$this->site;
        $content = $this->curl->getUrl($str);
        //<div class="path2root"><a href="http://yaca.yandex.ru/yca/cat/">Каталог</a> /<a href="http://yaca.yandex.ru/yca/cat/Media/">СМИ</a> /<h1><a href="http://yaca.yandex.ru/yca/cat/Media/Other/">Прочее</a></h1></div>
        if (preg_match('#Open Directory Categories#isU', $content, $res))
            $result['dmoz'] = 'Присутствует';
        else
            $result['dmoz'] = 'Отсутствует';


        /*
        Каталог Mail.ru
        в адресной строке вводим: http://search.list.mail.ru/?q=seonews.ru (домен без www)
        если найдены результаты, значит, сохраняем значения присутствия в каталоге.
         */

        $str = 'http://search.list.mail.ru/?q='.$this->site;
        $content = $this->curl->getUrl($str);
        $search = iconv('utf-8', 'windows-1251','#Не найдено ни одного сайта по Вашему запросу#isU');

        if (!preg_match($search, $content, $res))
            $result['mailru'] = 'Присутствует';
        else
            $result['mailru'] = 'Отсутствует';

        /**
        Рамблер ТОП100
        в адресной строке вводим:
        http://top100.rambler.ru/?pageCount=50&query=%22site.ru%22 (домен без www)
        если среди найденных результатов есть искомый сайт с адресом вида (http://site.ru или http://www.site.ru), значит, сохраняем значения присутствия в каталоге.
         */
        $str = 'http://top100.rambler.ru/?pageCount=50&query=%22'.$this->site.'%22';
        $content = $this->curl->getUrl($str);

        if (!preg_match('#К сожалению, не найдено ни одного ресурса по заданным критериям#isU', $content, $res))
            $result['rambler'] = 'Присутствует';
        else
            $result['rambler'] = 'Отсутствует';

        return $result;
    }

    /*
    *    8.	Регион сайта
    *    Если сайт есть в Яндекс.Каталоге, то в адресной строке вводим:
    *    http://search.yaca.yandex.ru/yandsearch?text=site.ru&rpt=rs2
    *    получаем список результатов, в нём ищем нужный сайт (http://site.ru или http://www.site.ru),
    * если результатов больше 10, а искомый сайт не найден, переходим на след. страницу, до тех пор, пока не найдём искомый.
    *  Сохраняем значение «Регион».
    *    Если сайта нет в Яндекс.Каталоге, кол-во страниц в Яндексе > 0, и его главная страница в индексе
    * (в адресной строке вводим: http://yandex.ru/yandsearch?text=url%3Ahttp%3A%2F%2Fsite.ru, результат > 0),
    *  то мы начинаем перебирать текстовое содержимое главной страницы анализируемого сайта, в пределах тега <body>,
    *  исключая содержимое тегов <a>…</a> и тегов <noindex> по 6 слов (включая предлоги)  проверяя результаты в Яндекс,
    * методом вставки текста в поисковую строку, до тех пор, пока не встретим результат с регионом анализируемого сайта,
    * но не более 15 запросов, с параметром numdoc=50 (т.е. должно выводиться по 50 результатов).
    * В случае нахождения результата с указанным регионом – сохраняем значение.
    */

    public  function getSiteRegion(){
        $ya_str = 'http://search.yaca.yandex.ru/yandsearch?text='.$this->site.'&rpt=rs2';
        $region = 'Не определено';
        $content  = $this->ya->getRaw($ya_str);

        if(preg_match('#<div class="z-counter">Найдено по описаниям сайтов — 0</div>#isU',$content)):
            if ($this->getYaPageIndex() <= 0 ) return $region;

            $content =  $this->curl->curlGET2($this->site);
            $text1 = f_passage_prepare($content, 'windows-1251');
		    $text1 = f_a_cut($text1, 'windows-1251');
            preg_match('#<body>(.*)</body>#is', $text1, $text1);

            if(isset($text1[1]))
                $text1 = $text1[1];

            $text1 = explode('+cut+', $text1);
            foreach ($text1 as $key => &$value):
                $value = preg_replace('#<.*>#isU', '', $value);
                $value = preg_replace('#&nbsp;#isU', ' ', $value);
                $value = preg_replace('#&mdash;#isU', ' ', $value);
                $value = preg_replace('#&raquo;#isU', ' ', $value);
                $value = preg_replace('#&laquo;#isU', ' ', $value);
            endforeach;
            unset($key, $value);

            $text1 =  array_map('trim', $text1);
            $queryCounter = 1;


            foreach($text1 as $key => $value):
                if ($value == "") continue;
                if(count(preg_split('#\s+#', $value))<6) continue;


                $parsed = $this->ya->getParsed($value, 50);
                foreach ($parsed as $ykey => $yvalue ):
                    if(preg_match('#'.$this->site.'#is', $yvalue['page'])&&($yvalue['region'] != '')):
                            $region = $yvalue['region'];
                            break;
                    endif;
                endforeach;

                if($region) break;
                if($queryCounter >= 15) break;

            $queryCounter++;
            endforeach;

        else:
            $region = array();
            preg_match_all('#<a class="b-head-userinfo__link" href=".*">Регион</a>:(.*)</div></td></tr>#isU',$content, $region);
            $region = trim($region[1][0]);
        endif;


        return $region;

    }

      /**
      *  9.	Дубликаты главной страницы
      *
      *  В адресной строке вводим следующие урл анализируемого сайта:
      *
      *  http://site.ru/index.html
      *  http://site.ru/index.htm
      *  http://site.ru/index.php
      *  http://site.ru/default.asp
      *  http://site.ru/index.php3
      *  http://site.ru/index.phtml
      *  В результате перебора должен быть либо 301 редирект, на http://site.ru, либо 404 ошибка.
      *  Если результат отличен от 301 или 404, то сохранить адрес дубликата.
     * @return array
     */
    public  function getDublicates(){
        $result = array();
        $a = array(
            'index.html',
            'index.htm',
            'index.php',
            'default.asp',
            'index.php3',
            'index.php4',
            'index.php5',
            'index.phtml',
        );
        foreach($a as $page):
            $res = $this->curl->getUrl('http://'.$this->site.'/'.$page);
            if ( $this->curl->getLastStatus() !== 404  && $this->curl->getLastStatus() !== 301  )
            $result[] = 'http://'.$this->site.'/'.$page;
        endforeach;
        return $result;
    }

/*
 * 10.	 Основное зеркало
 * В адресной строке вводим:
 * http://yandex.ru/yandsearch?site=site.ru&numdoc=50
 * и далее, при наличии, перебираем не более 5 страниц. Если адреса страниц преимущественно с www, значит в качестве
 * основного зеркала выбираем http://www.site.ru, если без, значит http://site.ru
 * Если в списке полученных адресов страниц присутствуют как адреса с www так и без, значит сохраняем так же значение,
 * что основное зеркало корректно не определено.
 */
   public function  mainMirror(){

       $ya_str = 'http://yandex.ru/yandsearch?site='.$this->site.'&numdoc=50&lr=213';
       $content  = $this->ya->getRaw($ya_str);

       $pages = array();
       preg_match_all('#<strong class="b-head-logo__text">(.*)</strong>#sUi', $content, $pages );
       preg_match('#\d+#',$pages[1][0], $pages);// закладываемся на то что по несколько тысяч страниц нам не нужно
       $pages = (int) ($pages[0]/50);
       if ($pages > 5 ) $pages = 5;
       if ($pages < 1 ) $pages = 1;
       $result = array();

       for ($i = 0; $i<$pages; $i++):
           flush();
           if ($i)
                $ya_str = 'http://yandex.ru/yandsearch?p=$i&site='.$this->site.'&numdoc=50';
           else
                $ya_str = 'http://yandex.ru/yandsearch?site='.$this->site.'&numdoc=50';

           $content  = $this->ya->getRaw($ya_str);
           $ex = array();
           preg_match_all("#b-serp-item__title-link\" tabindex=\"2\" href=\"(.+)\" onmousedown#isU", $content  , $ex);
           $ex = array_map('trim', $ex[1]) ;

           foreach ($ex as $key => $value):
               $temp = parse_url($ex[0]);
               @$result[$temp['host']]++;
           endforeach;

       endfor;

       $mirror = array_keys($result);
       if (count($result) > 2) return 'Не определено';
       if (count($result) == 1) return $mirror[0] ;

       if ($result[$mirror[0]]/$result[$mirror[1]] < 0.75) return $mirror[1];
       if ($result[$mirror[1]]/$result[$mirror[0]] < 0.75) return $mirror[0] ;
       return 'Не определено';
   }



    /*
     * 11.	 Robots.txt
     *   В адресной строке вводим следующий урл:
     *   http://site.ru/robots.txt
     *   если выдаётся 404 ошибка, то сохраняем значение, что файл отсутствует, если файл доступен, то проверяем его содержимое.
     *   Ищем директиву User-agent, её правильный вид должен быть таков:
     *   User-agent: *
     *   Ищем директивы Disallow и Allow, если в файле встречается:
     *   Disallow: /
     *   Или
     *   Allow: (пустое значение)
     *   То сохраняем значение, что директивы найдены и указан запрет к индексации всего сайта.
     *   Ищем директиву Host, её правильный вид:
     *   Host: site.ru
     *   Если какой-либо из директив не была указана, так же сообщаем об этом.
     */
      public  function RobotsTXT(){
          $result = array();
          $str = 'http://'.$this->site.'/robots.txt';
          $content = $this->curl->getUrl($str);
          if ($this->curl->getLastStatus() == 404)
              return array('Файл'=> 'Отсутсвует');
          else
              $result['Файл'] = 'Присутствует';

          if (!preg_match('#User-agent#U', $content, $res)){
              $result['Useragent'] = 'Отсутсвует';
          }
          if (!preg_match('#Disallow#U', $content, $res)){
              $result['Disallow'] = 'Отсутсвует';
          }
          if (!preg_match('#Allow#U', $content, $res)){
              $result['Allow'] = 'Отсутсвует';
          }
          if (!preg_match('#Host#U', $content, $res)){
              $result['Host'] = 'Отсутсвует';
          }

          if (preg_match('#Allow:\s#U', $content, $res))
              $result['Allow'] = 'Присутсвует, указан запрет к индексации всего сайта';

          if (preg_match('#Disallow:.*/$#U', $content, $res))
              $result['Disallow'] = 'Присутсвует, указан запрет к индексации всего сайта';

          if (!preg_match('#User-agent: \*#U', $content, $res) && !isset($result['useragent']))
              $result['Useragent'] = 'Неверное значение';

          if (!preg_match('#Host: .*'.$this->site.'#U', $content, $res) && !isset($result['host']))
              $result['Host'] = 'Неверное значение';
          else
              $result['Host'] = 'Присутствует';

          return $result;
      }

    /**
     *
     *12.	 .htaccess
     *   В адресной строке вводим следующий урл:
     *   http://site.ru
     *   и
     *   http://www.site.ru
     *   должен происходить 301 редирект, если не происходит – сохраняем значение 301 редирект – отсутствует.
     *   Так же в адресной строке вводим:
     *   http://site.ru/dasfsdfasdfasdf
     *   должна отдаваться 404 ошибка, если этого не происходит, сохраняем значение – 404 ошибка – не обрабатывается.
     */

    public  function htaccess(){

        $result = array();

        $this->curl->setFollowLocation(0);

        $this->curl->curlGET2($this->site);
        $status = $this->curl->getLastStatus();

        $this->curl->curlGET2('www.'.$this->site);
        $status2 = $this->curl->getLastStatus();

        if(($status != 301) && ($status2 != 301)):
            $result['redirect'] = '301 редирект – не прописан';
        else:
            $result['redirect'] = '301 редирект прописан';
        endif;

        $this->curl->setFollowLocation(1);


        $this->curl->curlGET2($this->site.'/dasfsdfasdfasd123f');
        $status = $this->curl->getLastStatus();
        if($status != 404):
            $result['redirect404'] = '404 ошибка – не обрабатывается.';
        else:
            $result['redirect404'] = '404 ошибка – обрабатывается.';
        endif;

        return $result;

    }//public  function htaccess(){

    /**
     *
     *
     * 13.	 Аффилиаты
     *
     *   В адресной строке вводим следующий урл:  http://tools.promosite.ru/use/clones.php
     *
     *   авторизуемся:
     *   Логин: SmitHAX
     *   Пароль: 159632
     *
     *   В графе имя домена вводим site.ru (без www), поле код с картинки – вводим через сервис antigate (узнать у Димы).
     *   Если есть аффилиаты получаем их список, далее по каждому из аффилиатов производим проверку, вводим в адресную строку следующий url:
     *   http://yandex.ru/yandsearch?text=аффилиат%20|%20site.ru&rd=0 (оба адреса без www).
     *   Если в топ10 только 1 домен из двух, значит сохраняем адрес аффилиата
     *
     * @return array
     */
    public  function getAffiliats(){
        global $CFG;
        $result = array();

        $str       = 'http://tools.promosite.ru/use/clones.php';
        $login_str = 'http://tools.promosite.ru/account.php?what=enter';
        // Авторизуемся
        $post      = array('u_login' => 'smithax', 'u_password' => '159632');
        $this->curl->cookie_file = $CFG->datadir.'/'.$this->ya->rand_string().".cookie";
        $content = $this->curl->curlPost($login_str, $post);

        $k_id    = array();
        $content = $this->curl->curlGET2($str);
        $pattern = '#name="k_id" value="(.*)" id="k_id"#isU';
        preg_match($pattern, $content, $k_id);

        // запрашиваем аффилиатов
        $tfile = $CFG->datadir."/queryGroups/".$this->ya->rand_string(10).".gif";
        file_put_contents($tfile, $this->curl->curlGET2('http://tools.promosite.ru/kcaptcha/tools_kcaptcha.php?kcaptcha='.$k_id[1]));

        do{
            $captcha = $this->curl->recognize($tfile);
        }while(!$captcha);


        /***
            host	loveplanet.ru
            k_code	6vhk9
            k_id	16838737
        */

        $post = array('host' => $this->site,'k_code' => $captcha, 'k_id' => $k_id[1]);


        $content = $this->curl->curlPost($str, $post);

        $res = array();


        //table border="1" cellspacing="0" cellpadding="5"
        preg_match('#<table border="1" cellspacing="0" cellpadding="5">(.*)<form action="" method="post" width="100%">#is' , $content, $res);
        if(!isset($res[0]))
            return array();
        preg_match_all('#<strong>(.*)</strong>#isU',$res[0],$res);


        /*
         *   Если есть аффилиаты получаем их список, далее по каждому из аффилиатов производим проверку, вводим в адресную строку следующий url:
         *   http://yandex.ru/yandsearch?text=аффилиат%20|%20site.ru&rd=0 (оба адреса без www).
         *   Если в топ10 только 1 домен из двух, значит сохраняем адрес аффилиата
         *
         */
        foreach($res[1] as $key => $affiliat ):
            if ($key > 9 ) break;// если больше десяти аффилиатов то и так все ясно

            $str   = "http://yandex.ru/yandsearch?text=$affiliat%20|%20{$this->site}&rd=0";
            $links = $this->ya->getParsed($str);

            $affiliatFound = false;
            $siteFound     = false;

            foreach($links as $key => $link):

                if (preg_match("#$affiliat#is", $link['page']))
                    $affiliatFound = true;
                if (preg_match("#$this->site#is", $link['page']))
                    $siteFound     = true;

            endforeach;

            if ($affiliatFound xor $siteFound)//Если в топ10 только 1 домен из двух, значит сохраняем адрес аффилиата
                $result[] = $affiliat;

        endforeach;

        return $result;

    }




    public function checkUnique($text){
        if (!is_array($text))
            $text = array($text);

        $result = array();
        foreach($text as $tt):
            $tt = preg_split('#\s+#', $tt);
            if(count($tt) < 6 ) continue;

            for($i = 0, $max = count($tt)-6; $i <= $max; $i++):
                $text   = $tt[$i].' '.$tt[$i+1].' '.$tt[$i+2].' '.$tt[$i+3].' '.$tt[$i+4].' '.$tt[$i+5];
                $text   = '"'.$text.'"';

                logger::AddRecord($text, 1);

                $this->ya->setInterface(false);

                $rez    = $this->ya->getParsed($text);

                foreach ($rez as $r):
                    if (!preg_match("#$this->site#is", $r['page'])):
                        $result[$text][] = $r['page'];
                    endif;
                endforeach;//foreach ($rez as $r):
                break;
            endfor;//for($i = 0, $max = count($tt)-6; $i<$max; $i++):
            break;
        endforeach;//foreach($text as $tt):
        return $result;
    }


    private function parseLinks(&$data, $linkscount){
           $count = 1;

        foreach($data as $uri => $tt):
            $uriTemp = $uri;


            if($data[$uri]['text'] !== '') continue;
            $tt = $this->parser->parseURL($uriTemp);
            if (empty($tt)) return;

            $data[$uri]['text']  = $tt['text'];
            $data[$uri]['links'] = $tt['links'];

            foreach($tt['links'] as $link):
                if(!array_key_exists($link, $data)):
                    $data[$link]['links'] = array();
                    $data[$link]['text'] ='';
                endif;
            endforeach;

            if($count >= $linkscount) break;
            $count++;
        endforeach;

    }//private function parseLinks(){

    /**
     * 14.	 Уникальность контента
     * Получаем список внутренних ссылок второго уровня вложенности (2 УВ) с главной страницы (до 100)
     * C каждой полученной ссылки начинаем перебирать текстовое содержимое страницы анализируемого сайта, в пределах тега <body>,
     * исключая содержимое тегов <a>…</a> и тегов <noindex> по 6 слов (включая предлоги)  проверяя результаты в Яндекс, методом вставки слов в
     * поисковую строку в кавычках: «слово1 слово2 слово3 слово4 слово5». Слова выбираем через каждые 20 слов.
     * Если в выдаче яндекса оказывается сайт отличный от анализируемого, сохраняем адрес неуникальной страницы.
     *
     */

    public  function getContentUnique(){
        $result = array();
        echo '  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />';

        $UV = array();

        $this->parser = new parser();

        $links[$this->site]['text']  = '';
        $links[$this->site]['links'] = array($this->site);



        $this->parseLinks($links, 100);


        /*
            Если ссылок 2 УВ больше 80
            С каждой ссылки 2 УВ собираем по 10 ссылок 3 УВ. С каждой ссылки 3 УВ собираем по 1 ссылки 4 УВ.
            Если ссылок 2 УВ 50-79
            С каждой ссылки 2 УВ собираем по 15 ссылок 3 УВ. С каждой ссылки 3 УВ собираем по 2 ссылки 4 УВ.
            Если ссылок 2 УВ 20-49
            С каждой ссылки 2 УВ собираем по 15 ссылок 3 УВ. С каждой ссылки 3 УВ собираем по 4 ссылки 4 УВ.
            Если ссылок 2 УВ 0-19
            С каждой ссылки 2 УВ собираем по 25 ссылок 3 УВ. С каждой ссылки 3 УВ собираем по 6 ссылки 4 УВ.
         */
        $linkscount = count($links[$this->site]['links']);

        if($linkscount > 100):
            $UV[3] = 10;
            $UV[4] = 1;
        elseif(($linkscount > 90)&&($linkscount <= 100)):
            $UV[3] = 10;
            $UV[4] = 1;
        elseif(($linkscount >= 50) &&($linkscount <= 79)):
            $UV[3] = 15;
            $UV[4] = 2;
        elseif(($linkscount >= 20) &&($linkscount <= 49)):
            $UV[3] = 15;
            $UV[4] = 4;
        elseif(($linkscount >= 1) &&($linkscount <= 19)):
            $UV[3] = 15;
            $UV[4] = 6;
        else:
            $UV[3] = 100;
            $UV[4] = 10;
        endif;

       $this->parseLinks($links, $UV[3]);
       $this->parseLinks($links, $UV[4]);

       $result = array();
        $i = 1;
       foreach($links as $link => $data):
            $text = $this->checkUnique($data['text']);
            if(!empty($text))
                $result[$link][] = $text;

            if($i >= 50) break;
            $i++;
       endforeach;

        return $result;
    }//public  function getContentUnique(){


    public function setInterface($int){
        $this->ya->setInterface($int);
        $this->gooo->setInterface($int);
        $this->rambler->setInterface($int);
    }



    /**
     *
     * 16.	 Основные конкуренты и их параметры

	По каждому введённому запросу происходит сохранение результатов топ10 Яндекс, исключая wikipedia
	Далее полученные результаты сверяются и ищутся совпадения.
    Если в полученном списке находится анализируемый сайт, то он удаляется из списка.
	Результаты сортируются по кол-ву совпадений.
	Для каждого запроса получается его частота по worstat.yandex.ru при помощи запроса вида «”!поисковый !запрос”» (в кавычках и перед каждым словом восклицательный знак).
	В случае если найденных совпадений по списку менее 10 сайтов, то удаляется наиболее частотный запрос, и так далее по убыванию, пока найденных результатов не станет минимум 10.
    В случае если совпадений найдено более 10, то выбираются 10 наиболее релевантных сайтов.
	Для каждого из 10 найденных производится анализ:
	Кол-во страниц в индексе Яндекс
	Возраст домена
	тИЦ сайта
	Входящих ссылок по Yahoo

    Каждый из 4 массивов по 10 результатов сортируется по возрастанию. Из каждого массива суммируем 4,5,6,7
    результаты и получившуюся сумму делим на 4. В итоге у нас получается 4 числа:
	Среднее кол-во страниц в индексе Яндекс
	Средний возраст домена
    Средний тИЦ сайта
	Среднее кол-во входящих ссылок по Yahoo
     *
     * @param $base_audit_id
     * @return array
     *
     */
    public function getConcurrency($base_audit_id){

        $result = array();

        $base_audit_id = intval($base_audit_id);
        $queries = feth_all("SELECT `qqq` FROM `base_audit` WHERE `id`  = $base_audit_id ");
        $queries = explode("\n", $queries[0]['qqq']);

        $sites = array();
        foreach($queries as $query):
            $query = trim($query);

            if(!$query) continue;

            foreach($this->ya->getParsed($query, 10) as $site):
                if(!preg_match('#'.$this->site.'#i', $site['page'])&& !preg_match('#wikipedia#i', $site['page']))
                    $tt = parse_url($site['page']);
                if(isset($tt['host']))
                    $sites[]= $tt['host'];
                else
                  $sites[]= $tt['path'];
            endforeach;
        endforeach;
        $sites = array_count_values($sites);
        arsort($sites);

        $i = 1;
        $age = array();
        $tic = array();
        $page = array();


        foreach($sites as $site => $v):

            $site = preg_replace('#www\.#i','', $site);

            // прикольненько
            $result[$site]['age']  = $age[]  =  $this->GetDomenAge($site);
            $result[$site]['tic']  = $tic[]  =  $this->GetDomenTic($site);
            $result[$site]['page'] = $page[] =  $this->getYaPageIndex($site);

            if($i >= 10) break;
            $i++;
        endforeach;

        asort($age);
        asort($tic);
        asort($page);

        array_pop($age);array_pop($age);   array_shift($age);array_shift($age);
        array_pop($tic);array_pop($tic);   array_shift($tic);array_shift($tic);
        array_pop($page);array_pop($page); array_shift($page);array_shift($page);

        $result['middle']['age']  = round(array_sum($age)/count($age));
        $result['middle']['tic']  = round(array_sum($tic)/count($tic), -1);
        $result['middle']['page'] = round(array_sum($page)/count($page)-1);

        return $result;

    }//public function getConcurrency($base_audit_id){


}//class
