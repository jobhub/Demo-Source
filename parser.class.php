<?php
/**
 * Created by JetBrains PhpStorm.
 * User: dlitvin
 * Date: 07.02.12
 * Time: 10:16
 * To change this template use File | Settings | File Templates.
 */
require_once ("logger.class.php");
require_once('curl.class.php');


/**
 * Класс для парсинга все что парсится, окромя пожалуй поисковой выдачи.
 *
 */
class parser {

    protected $curl;

    public function __construct(){
        $this->curl = new curl2();

    }

    /**
     * Получить все ссылки в тексте.
     *
     * @return array
     *
     */
    public function getLinksFromText($html){

        $result = array();
        $html = preg_replace('/<noindex>(.*)<\/noindex>/isU','', $html);

        $doc = new DOMDocument();
        if (!@$doc->loadHTML($html)):
            throw new Exception('Не загрузили html!');
        endif;

        $xpath = new DOMXpath($doc);
        $elements = $xpath->query('//a/@href');
        foreach ($elements  as $el):
            if ($el->nodeValue == '/' ) continue;
            if ($el->nodeValue == '' ) continue;
            if (
                  (stripos($el->nodeValue,'.jpg')   !== false)
                ||(stripos($el->nodeValue,'.png')   !== false)
                ||(stripos($el->nodeValue,'.gif')   !== false)
                ||(stripos($el->nodeValue,'.ico')   !== false)
                ||(stripos($el->nodeValue,'.jpeg')  !== false)
                ||(stripos($el->nodeValue,'mailto') !== false)
                ||(stripos($el->nodeValue,'#')      !== false)
                ||(stripos($el->nodeValue,'javascript') !== false)
            )
            continue;
            $result[] = $el->nodeValue;
        endforeach;

        return $result;
    }

    /**
     * Получить ссылки на ВНУТРЕННИЕ страницы сайта из текста странички
     * URL ПЕРЕДАВАТЬ ПОЛНОСТЬЮ С WWW и http://
     * @return array
     *
     *
     */
    public function getInternalLinksFromText($text, $uri){
        if(!preg_match('#http#is', $uri))
            $uri = 'http://'.$uri;

        $result  = array();
        try {
        $links = $this->getLinksFromText($text);
        } catch (Exception $e) {
            throw new Exception('НЕ смог загрузить html'. $text.' по адресу '.$uri);
        }

        $site  = '';
        $ttt =  preg_match('#^(?:http://)?([^/?]+)#i', $uri, $site);

        if (!$ttt):
             throw new Exception("парсер не смог определить сайт в ссылке $uri");
        endif;

        $site = $site[1];

        foreach ($links as $link)://вычленить внутренние ссылки

             if (false !== stripos($link, $site )):// абсолютные или относительные ссылки
               $result[] = $link;
             else:
                 $valid = "/^(https?:\/\/)?([\da-z\.-]+)\.([a-z\.]{2,6})([\/\w \.-]*)*\/?$/";
                if (!preg_match($valid, $link)){
                    $ggg  = parse_url($link);
                    $ggg2 = parse_url($uri);
                    $resultUri= '';
                    if (isset($ggg['host'])){
                        if ($ggg['host'] !== $ggg2['host'] ) continue;
                        $resultUri =  'http://'.$ggg['host'];
                    }
                    else
                        if (isset($ggg2['host']))
                            $resultUri = 'http://'.$ggg2['host'];

                    if (isset($ggg['path']))
                        $resultUri .= $ggg['path'];
                    else
                        $resultUri .= $ggg2['path'];

                    if (isset($ggg['query']))
                        $resultUri .='?'.$ggg['query'];


                    $result[] = $resultUri;
                }
                else {//это сильно похоже на внешнюю ссылку
                    logger::AddRecord("Парсер не смог понять тип (внутрення/внешняя) ссылки $link");
                    continue;
                }

             endif;
        endforeach;

        return $result;

    }//public function getInternalLinksFromText($text, $uri){



    /**
     * Распарсить страницу по переданному адресу и получить с нее все внутренние ссылки
     *
     * @throws Exception
     * @param $uri
     * @return array
     *
     */

    public function getInternalLinksFromURI($uri){
        if(!preg_match('#http#is', $uri))
            $uri = 'http://'.$uri;

        $this->curl->setInterface(false);
        $old = $this->curl->setFollowLocation(0);

        $html   = $this->curl->curlGET2($uri);
        if (($this->curl->getLastStatus()  == 404) ||  ($this->curl->getLastStatus() == 302)||  ($this->curl->getLastStatus() == 301)):
             logger::AddRecord('Страница '.$uri.' вернула '.$this->curl->getLastStatus());
             return array();
        endif;

        $result = $this->getInternalLinksFromText($html, $uri);

        $this->curl->setFollowLocation($old);
        return $result;
    }//public function getInternalLinksFromURI($uri){


    /**
     * @param $html
     * @return array
     */

    public function getChunksFromHtml($html){
        $html = trim($html);
        if(!$html)
            throw new Exception('Функция getChunksFromHtml  получила пустой текст!');

        $result = array();
        $text = '';
        preg_match('#<body>(.*)</body>#is', $html, $text);

        $text = trim($text[1]);
        $text = f_passage_prepare($text, 'windows-1251');
		$text = f_a_cut($text, 'windows-1251');
        $text = explode('+cut+', $text);

        foreach ($text as $key => &$value):
                $value = preg_replace('#<.*>#isU', '', $value);
                $value = preg_replace('#&nbsp;#isU', ' ', $value);
                $value = preg_replace('#&mdash;#isU', ' ', $value);
                $value = preg_replace('#&raquo;#isU', ' ', $value);
                $value = preg_replace('#&laquo;#isU', ' ', $value);
                $value = preg_replace('#&copy;#isU', ' ', $value);
                $value = preg_replace('#&ndash;#isU', ' ', $value);
        endforeach;
        unset($key, $value);

        foreach ($text as $t):
            $t = trim($t);
            if($t !== '') $result[] = $t;
        endforeach;

        return $result;

    }//public function getChunksFromHtml($html){


    private function f_passage_prepare($text_tmp, $coding) {
        $text_tmp = preg_replace('/<noindex>(.*)<\/noindex>/isU','', $text_tmp);
        $text_tmp = preg_replace('#<noscript(.*)</noscript#isU','', $text_tmp);

        $text_tmp = preg_replace('/<script (.*)>(.*)<\/script>/isU','', $text_tmp);
        $text_tmp = preg_replace('/<script>(.*)<\/script>/isU','', $text_tmp);
        $text_tmp = preg_replace('/<div (.*)>/isU','', $text_tmp);
        $text_tmp = preg_replace('/<style (.*)>(.*)<\/style>/isU','', $text_tmp);
        $text_tmp = preg_replace('/<!--(.*)-->/isU','', $text_tmp);
        /******************************************************************************/


        //$text_tmp .= ' полет. гнездо! кукушки? ';
        $text_tmp = implode('+cut+.', explode('. ', $text_tmp));
        $text_tmp = implode('+cut+!', explode('! ', $text_tmp));
        $text_tmp = implode('+cut+?', explode('? ', $text_tmp));
        //после знака должен присутствовать пробел

        $text_tmp = implode('+cut+<a', explode('<a', $text_tmp));
        $text_tmp = implode('+cut+<h1', explode('<h1', $text_tmp));
        $text_tmp = implode('+cut+<h2', explode('<h2', $text_tmp));
        $text_tmp = implode('+cut+<h3', explode('<h3', $text_tmp));
        $text_tmp = implode('+cut+<h4', explode('<h4', $text_tmp));
        $text_tmp = implode('+cut+<h5', explode('<h5', $text_tmp));
        $text_tmp = implode('+cut+<h6', explode('<h6', $text_tmp));
        $text_tmp = implode('+cut+<div', explode('<div', $text_tmp));
        $text_tmp = implode('+cut+<span', explode('<span', $text_tmp));
        $text_tmp = implode('+cut+<p', explode('<p', $text_tmp));
        $text_tmp = implode('+cut+<td', explode('<td', $text_tmp));
        $text_tmp = implode('+cut+<tr', explode('<tr', $text_tmp));
        $text_tmp = implode('+cut+<br', explode('<br', $text_tmp));
        $text_tmp = implode('+cut+<table', explode('<table', $text_tmp));

        $text_tmp = implode('+cut+</a', explode('</a', $text_tmp));
        $text_tmp = implode('+cut+</h1', explode('</h1', $text_tmp));
        $text_tmp = implode('+cut+</h2', explode('</h2', $text_tmp));
        $text_tmp = implode('+cut+</h3', explode('</h3', $text_tmp));
        $text_tmp = implode('+cut+</h4', explode('</h4', $text_tmp));
        $text_tmp = implode('+cut+</h5', explode('</h5', $text_tmp));
        $text_tmp = implode('+cut+</h6', explode('</h6', $text_tmp));
        $text_tmp = implode('+cut+</div', explode('</div', $text_tmp));
        $text_tmp = implode('+cut+</span', explode('</span', $text_tmp));
        $text_tmp = implode('+cut+</p', explode('</p', $text_tmp));
        $text_tmp = implode('+cut+</td', explode('</td', $text_tmp));
        $text_tmp = implode('+cut+</tr', explode('</tr', $text_tmp));
        $text_tmp = implode('+cut+</br', explode('</br', $text_tmp));
        $text_tmp = implode('+cut+</table', explode('</table', $text_tmp));
        //$text_tmp = mb_strtolower(trim($text_tmp), $coding);
        return $text_tmp;
    }

    private function f_a_cut($text) {
        $out = '';
        if (substr_count($text, '<a ') and substr_count($text, '</a>')) {
        $arr = explode('<a ', $text);
        foreach ($arr as $v) {
                if (substr_count($v, '</a>')) {
                    $arr2 = explode('</a>', $v);
                    $out .= ' '.$arr2[1].' ';
                } else {
                    $out .= $v;
                }
            }
        } else {
            $out = $text;
        }
        return trim($out);
    }

    public function parseURL($url){
        $site = $url;
        if(!preg_match('#http#is', $url))
            $url = 'http://'.$url;
        $this->curl->setInterface(false);


        $html   = $this->curl->curlGET2($url);
        if(!$html) return array();

        if (($this->curl->getLastStatus()  == 404) ||  ($this->curl->getLastStatus() == 302)):
            logger::AddRecord('Страница '.$url.' вернула '.$this->curl->getLastStatus());
            return array();
        endif;

        $links = $this->getInternalLinksFromText($html, $url);
        $text  = $this->getChunksFromHtml($html);

        $result['links'] = $links;
        $result['text']  = $text;

        return $result;
    }//public function parseURL($url){

    function detect_encoding($string) {
        static $list = array('utf-8', 'windows-1251');

        foreach ($list as $item) {
            $sample = @iconv($item, $item, $string);
            if (md5($sample) == md5($string))
            return $item;
        }
        return null;
    }


}//class parser
