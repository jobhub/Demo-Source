<?php
require_once('curl.class.php');



class google extends curl2{
    public function seo_google_urls2 ($query, $numdoc = 10){

        global $CFG;
        $rnd = rand_string(10);
        $url ="http://www.google.ru/search?q=".rawurlencode($query)."&hl=ru&num=$numdoc";

        $this->cookie_file  = $CFG->datadir."/".$rnd.".txt";
		$count = 0;
        $results = array();
        $result = array();
		$doc = new DOMDocument();


	   // preg_match_all("|<h3 class=\"r\"><a href=\"([^\"].*)\".*class=l>(.*)<\/a><\/h3><div class=\"s\">(.*)<br>|isU", $file, $data);
        $xpathQuery = array();
		$xpathQuery[] = '//ol/li[@class="g"]/h3[@class="r"]/a/@href';
		$xpathQuery[] = '/html/body/p/a/@href';



        while (true){
                sleep(1);
                $this->setInterface(false);
                $file = $this->query($url);
               // $file = file_get_contents('query_group_daemon3');

                @$doc->loadHTML($file);
                $xpath = new DOMXPath($doc);
                foreach($xpathQuery as $q){
                    $results = $xpath->query($q);
                    if ($results->length) break;
                }

                $count++;
                if ($count == 5 ){
                    //file_put_contents('query_group_daemon3',$file);
                    echo '<br><b>file_put_contents</b><br>';
                    return false;
                }

                if (!$results->length) {
                    //file_put_contents('query_group_daemon3',$file);
                    echo '<br><b>file_put_contents</b><br>';
                    return false;
                }

                if ($results->length) break;


        }
        foreach($results as $r){
            if(false != stripos($r->nodeValue,'google')) continue;
            if (preg_match('#^/search\?q=#Ui',$r->nodeValue, $temp))continue;

            $temp = array();
            preg_match('#/url\?q=(.*)&sa=#Ui',$r->nodeValue, $temp);
            if($temp) $result[] = $temp[1];
            else      $result[] = $r->nodeValue;


        }


        return $result;
    }

    public  function getContent($url){
        return $this->query($url);
    }

    private  function query($url){
        $this->setInterface(false);
        $file = $this->getUrl($url);
        return $file;
    }

    public  function GetPr($site){

        if (!preg_match('#http://#', $site))
		    $url= 'http://'.$site;

		$ch = $this->CheckHash($this->HashURL($url));

		$file = "http://google.com/search?client=navclient-auto&ch=$ch&features=Rank&q=info:$site";
		//      tp://www.google.com/search?client=navclient-auto&features=Rank:&q=info:http://seop.ru&ch=753755094124

		$data = $this->getUrl($file);

		if(!$data || preg_match("/(.*)\.(.*)/i", $url)==0)
			return "N/A";            //If the Google data is unavailable, or the URL is invalid, return "N/A".
		//The preg_match check is a very basic url validator that only checks if the URL has a period in it.
		$rankarray = explode (":", $data);        //There are two line breaks before the PageRank data on the Google page

		$rank = isset($rankarray[2])?trim($rankarray[2]):'';        //Trim whitespace and line breaks.
		if($rank == "")
			return "N/A";            //Return N/A if no rank.

		return (int)$rank;
    }


/*
 * Genearate a hash for a url
 */
    private function HashURL($String){
        $Check1 = $this->StrToNum($String, 0x1505, 0x21);
        $Check2 = $this->StrToNum($String, 0, 0x1003F);

        $Check1 >>= 2;
        $Check1 = (($Check1 >> 4) & 0x3FFFFC0 ) | ($Check1 & 0x3F);
        $Check1 = (($Check1 >> 4) & 0x3FFC00 ) | ($Check1 & 0x3FF);
        $Check1 = (($Check1 >> 4) & 0x3C000 ) | ($Check1 & 0x3FFF);

        $T1 = (((($Check1 & 0x3C0) << 4) | ($Check1 & 0x3C)) <<2 ) | ($Check2 & 0xF0F );
        $T2 = (((($Check1 & 0xFFFFC000) << 4) | ($Check1 & 0x3C00)) << 0xA) | ($Check2 & 0xF0F0000 );

        return ($T1 | $T2);
    }

/*
 * genearate a checksum for the hash string
 */
    private function CheckHash($Hashnum){
        $CheckByte = 0;
        $Flag = 0;

        $HashStr = sprintf('%u', $Hashnum) ;
        $length = strlen($HashStr);

        for ($i = $length - 1;  $i >= 0;  $i --) {
            $Re = $HashStr{$i};
            if (1 === ($Flag % 2)) {
                $Re += $Re;
                $Re = (int)($Re / 10) + ($Re % 10);
            }
            $CheckByte += $Re;
            $Flag ++;
        }

        $CheckByte %= 10;
        if (0 !== $CheckByte) {
            $CheckByte = 10 - $CheckByte;
            if (1 === ($Flag % 2) ) {
                if (1 === ($CheckByte % 2)) {
                    $CheckByte += 9;
                }
                $CheckByte >>= 1;
            }
        }

        return '7'.$CheckByte.$HashStr;
    }
	/*
 * convert a string to a 32-bit integer
 */
    private function StrToNum($Str, $Check, $Magic){
        $Int32Unit = 4294967296;  // 2^32

        $length = strlen($Str);
        for ($i = 0; $i < $length; $i++) {
            $Check *= $Magic;
            //If the float is beyond the boundaries of integer (usually +/- 2.15e+9 = 2^31),
            //  the result of converting to integer is undefined
            //  refer to http://www.php.net/manual/en/language.types.integer.php
            if ($Check >= $Int32Unit) {
                $Check = ($Check - $Int32Unit * (int) ($Check / $Int32Unit));
                //if the check less than -2^31
                $Check = ($Check < -2147483648) ? ($Check + $Int32Unit) : $Check;
            }
            $Check += ord($Str{$i});
        }
        return $Check;
    }

}
