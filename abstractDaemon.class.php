<?php

/**
 * Created by JetBrains PhpStorm.
 * User: dlitvin
 * Date: 29.12.11
 * Time: 9:29
 * To change this template use File | Settings | File Templates.
 */
require_once("../config.php");
error_reporting(-1);
abstract class abstractDaemon {

    /**
     * @var int номер потока
     */
    protected $pid;

    /**
     * @var string  имя файла блокировки
     */
    private  $fpid;


    /**
     * @var int таймаут во время которого мы просто отваливаемся
     */
    protected $timeout = 1200;

    protected $debug = false;

    /**
     * @param $pid int номер потока
     */
    public function __construct($pid, $timeout = 1200, $debug = false){
        global $CFG;


        $this->timeout = $timeout;
        $this->debug   = $debug;
        $this->pid     = $pid;

        $name = get_class($this);

        logger::AddRecord("Начало работы демона $name с pid $pid ");

        $this->fpid = $CFG->pids."/{$name}_{$pid}.txt";


        $ff = @fopen($this->fpid, 'r+'); if ($ff) {$res = fread($ff, 10);  fclose($ff);} else {$res = "wait";}

        if (($res !== "wait") && (time()- filemtime($this->fpid) < $this->timeout  ))

        if(!$this->debug):
            $time = time()- filemtime($this->fpid);
            logger::AddRecord("res !== 'wait' и таймаут $time  ВЫХОД!!!");

            throw new Exception("res !== 'wait' и таймаут $time  ВЫХОД!!! \r\n");
        endif;

        file_put_contents($this->fpid, 'busy');
        db_query("SET NAMES utf8");
        set_time_limit($this->timeout);

    }//public function __construct($pid)


    /**
     * @abstract
     * @param array $params
     * @return void
     *
     * функция которую запускать
     */
    abstract function run(array $params);



    /**
     * @param bool $newDebugVlue
     *
     * @return void
     */

    public function setDebug($newDebugVlue = false){
        $this->debug = (boolean)$newDebugVlue;
    }//public function setDebug($newDebugVlue = false){


    public function refreshBusy(){
            file_put_contents($this->fpid, 'busy');
    }//public function refreshBusy(){

    /**
     *
     */
    function __destruct(){

       $ff = fopen($this->fpid, 'w+'); fputs($ff, "wait"); fclose($ff);
       logger::AddRecord('Штатное завершение работы демона...');
       logger::WriteToLog();
       flush();

    }//function __destruct(){



}
