<?php
/**
 * Created by JetBrains PhpStorm.
 * User: dlitvin
 * Date: 01.09.11
 * Time: 15:51
 * To change this template use File | Settings | File Templates.
 */
require_once('curl.class.php');


class rambler extends curl2 {


    private function getContent($url){
        global $CFG;
        $rnd = rand_string(10);
        $ban_count = 4;


            $ex = array();

            $this->setInterface(false);
            $this->cookie_file  = $CFG->datadir."/".$rnd.".txt";

            $file = $this->getUrl($url);
        return $file;
    }


    public  function query($url){
        return $this->getContent($url);
    }



}
