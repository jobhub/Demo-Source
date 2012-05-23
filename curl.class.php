<?php

@include_once("../config.php");

class curl2 {



    private $_ch = false;

    /*
     * URL который запросить
     *
     */
    public $url;
    /**
     * @var string
     *
     * IP адрес через который посылается запрос
     *
     */
    public $int = false;


    public $referrer = '';

    public $cookie_file = '';

    public $proxy = '';

    public $timeout = 15;

    public $header = 0;

    public $ua = 0;

    public $lastHeaders = '';
    public $lastInfo = '';
    /**/
    private  $lastStatus;
    private  $lastError;

    public  function __construct($url = '', $referrer='', $cookie_file='', $proxy='', $timeout=15, $header=0, $int = 0){
        $this->url         = $url;
        $this->referrer    = $referrer;
        $this->cookie_file = $cookie_file;
        $this->proxy       = $proxy ;
        $this->timeout     = 15;
        $this->header      = $header;
        $this->int         = $int;
    }



    /**
     *
     * Выбрать случайный интерфейс.
     *
     *
     * @param bool $int
     * @return void
     */
    public  function  setInterface($int = false){
        global $CFG;

        if ($int == false)
            $this->int = trim($CFG->ips[rand(0,count($CFG->ips)-1)]);

        else
            $this->int = $int;

    }

    /**
     *
     * Настроить курл для выполнения GET запроса
     * @throws Exception
     * @param $url
     * @return mixed
     */
    public function curlGET2($url = '') {
        if (!$url && !$this->url)
            throw new Exception('Не указан URL!');
         if ($url)
            $this->url = $url;

        $this->_ch = curl_init($this->url);

        return $this->curlExec($this->_ch);
    }

    /**
     *
     * Настроить курл для выполнения POST запроса
     *
     * @throws Exception
     * @param string $url
     * @param array $POSTdata
     * @return mixed
     */
    public  function  curlPost($url = '', $POSTdata = array()){

        $url = trim($url);

        if (empty($POSTdata))
             throw new Exception('Нет данных!');
        if (!$url && !$this->url)
             throw new Exception('Не указан URL!');

        if ($url)
            $this->url = $url;
        logger::AddRecord('curlPost '.$url);
        $this->_ch = curl_init($this->url);

        curl_setopt($this->_ch, CURLOPT_POST, 1);
        curl_setopt($this->_ch, CURLOPT_POSTFIELDS, $POSTdata);


        return $this->curlExec($this->_ch);


    }


    /**
     * @param $ch
     *
     * Выставить общие настройки и выполнить курловый запрос
     *
     * @return mixed
     */
    private function curlExec(){
        global $CFG;


        if (!$this->int)
            $this->setInterface(false);

        if (!$this->ua)
            $this->ua  = $this->setNewUserAgent();

        $header[0] = "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8";
		$header[] = "Cache-Control: max-age=0";
		$header[] = "Connection: keep-alive";
		$header[] = "Keep-Alive: 300";
		$header[] = "Accept-Charset: windows-1251,utf-8;q=0.7,*;q=0.7";
		$header[] = "Accept-Language: en-us,en;q=0.5";
		$header[] = "Pragma: "; // browsers keep this blank.


        curl_setopt($this->_ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($this->_ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($this->_ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($this->_ch, CURLOPT_SSL_VERIFYPEER, false);

        curl_setopt($this->_ch, CURLOPT_INTERFACE,  $this->int);
        curl_setopt($this->_ch, CURLOPT_USERAGENT,   $this->ua);
        curl_setopt($this->_ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($this->_ch, CURLOPT_HTTPHEADER,  $header);

        if($this->referrer) curl_setopt($this->_ch, CURLOPT_REFERER, $this->referrer);
        if($this->header)   curl_setopt($this->_ch, CURLOPT_HEADER,  1);
        if($this->proxy)    curl_setopt($this->_ch, CURLOPT_PROXY,   $this->proxy);

        /***************************************************************/
        if(!$this->cookie_file)
            $this->cookie_file =  $CFG->datadir."/yandexclass/z_".$this->int."_cookie.txt";

        if(file_exists($this->cookie_file)):
            @chmod( $this->cookie_file, 0777);
        else:
            file_put_contents($this->cookie_file,' ');
            @chmod( $this->cookie_file, 0777);
        endif;

        logger::AddRecord('curlExec кукифайл  '.$this->cookie_file );
        /***************************************************************/
        curl_setopt($this->_ch, CURLOPT_COOKIEJAR,  $this->cookie_file);
        curl_setopt($this->_ch, CURLOPT_COOKIEFILE, $this->cookie_file);
        //curl_setopt($ch, CURLOPT_COOKIESESSION, '0');
        logger::AddRecord('curl_exec '.$this->url);

        $content = curl_exec($this->_ch);



        $this->lastStatus  = curl_getinfo($this->_ch, CURLINFO_HTTP_CODE);
        $this->lastError   = curl_error($this->_ch);
        $this->lastHeaders = curl_getinfo($this->_ch, CURLINFO_HEADER_OUT);
        $this->lastInfo    = curl_getinfo($this->_ch);


        if($content === false):

            logger::AddRecord("Курл не смог получить данные из url {$this->url}!");
            logger::AddRecord('Ошибка: '.$this->getLastError());
            logger::AddRecord('Статус: '.strval($this->getLastStatus()));
            logger::AddRecord('Интерфейс: '.strval($this->int));

            if(preg_match('#timed out#i', $this->lastError)):
                logger::AddRecord('Зафиксировать бан интерфейса в БД');
                db_query("INSERT INTO `banned_interface`(`interface`, `bancount`) VALUES('{$this->int}', 1) ON DUPLICATE KEY  UPDATE `bancount` = `bancount` + 1 ");
            endif;
        else:
            logger::AddRecord('получили таки контент');
        endif;

        curl_close($this->_ch);
        @chmod( $this->cookie_file, 0777);
        return $content;
    }


    public function getCookieFile(){
        global $CFG;
        if(!$this->int)
            $this->setInterface(false);

        if(!$this->cookie_file)
            $this->cookie_file =  $CFG->datadir."/yandexclass/z_".$this->int."_cookie.txt";

        return $this->cookie_file;
    }

    /**
     * @param $url
     * @return mixed
     */
    public  function getUrl($url){
        $this->url = $url;
        return $this->curlGET2();

    }


    /**
     * @param $url
     * @return void
     */
    public function setUrl($url){
        $this->url = $url;
    }


    /**
     * @return int
     */
    public  function  getLastStatus(){
        return $this->lastStatus;
    }

    public  function  getLastError(){
        return $this->lastError;
    }

    public  function setNewUserAgent(){
        global $CFG;
        $this->ua  = rtrim($CFG->ua[rand(0, count($CFG->ua)-1)]);
    }













    /**
     * @throws Exception
     * @return bool|string
     */
    public   function recognize($filename, $apikey = 'xxx', $is_verbose = true, $rtimeout = 10, $mtimeout = 120, $is_phrase = 0, $is_regsense = 0, $is_numeric = 0, $min_len = 0, $max_len = 0)
    {
	    if (!file_exists($filename))
	    {
		    if ($is_verbose) echo "file $filename not found\n";
            flush();
		    return false;
	    }
        $postdata = array(
            'method'    => 'post',
            'key'       => $apikey,
            'file'      => '@'.$filename, //полный путь к файлу
            'phrase'	=> $is_phrase,
            'regsense'	=> $is_regsense,
            'numeric'	=> $is_numeric,
            'min_len'	=> $min_len,
            'max_len'	=> $max_len,

        );
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,            'http://antigate.com/in.php');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT,        60);
        curl_setopt($ch, CURLOPT_POST,           1);
        curl_setopt($ch,  CURLOPT_INTERFACE, '92.38.195.130');
        curl_setopt($ch, CURLOPT_POSTFIELDS,     $postdata);
        $result = curl_exec($ch);

        if ($result == false):
             $header_ = 'MIME-Version: 1.0' . "\r\n" . 'Content-type: text/plain; charset=utf-8' . "\r\n";
             mail('v.kuznetsov@antarion.ru, rexmrz@gmail.com', 'Упал Антигейт!!!',curl_error($ch) , $header_);

             throw new Exception("ANTIGATE ERROR: ".curl_error($ch));
        endif;

        curl_close($ch);
        if (strpos($result, "ERROR")!==false)
        {
            if ($is_verbose) echo "server returned error: $result\n";
            flush();
            return false;
        }
        else
        {
            $ex = explode("|", $result);
            $captcha_id = $ex[1];
            if ($is_verbose) echo "captcha sent, got captcha ID $captcha_id\n";

            $waittime = 0;
            if ($is_verbose) echo "waiting for $rtimeout seconds\n";

            sleep($rtimeout);
            while(true)
            {
                $result = file_get_contents('http://antigate.com/res.php?key='.$apikey.'&action=get&id='.$captcha_id);
                if (strpos($result, 'ERROR')!==false)
                {
                    if ($is_verbose) echo "server returned error: $result\n";
                    return false;
                }
                if ($result=="CAPCHA_NOT_READY")
                {
                    if ($is_verbose) echo "captcha is not ready yet\n";
                    $waittime += $rtimeout;
                    if ($waittime>$mtimeout)
                    {
                        if ($is_verbose) echo "timelimit ($mtimeout) hit\n";
                        flush();
                        break;
                    }
                    if ($is_verbose) echo "waiting for $rtimeout seconds\n";
                    flush();
                    sleep($rtimeout);
                }
                else
                {

                    $ex = explode('|', $result);
                    if (trim($ex[0])=='OK'){
                        if ($is_verbose) echo "captcha OK (".trim($ex[1]).") continue execution";
                        flush();
                        return trim($ex[1]);
                    }
                    else {
                        if ($is_verbose) echo "captcha ERROR!";
                        flush();
                    }

                }
            }

            return false;
        }
    }// function recognize(

     function __destruct(){
       // @unlink($this->cookie_file);
    }

}
