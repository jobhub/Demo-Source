<?php
/**
 * Created by JetBrains PhpStorm.
 * User: dlitvin
 * Date: 20.01.12
 * Time: 13:51
 * To change this template use File | Settings | File Templates.
 */

/**
В самом низу устанавливается перехватчик исключений!
 */

error_reporting(-1);
class logger {


    const LOG_DISPLAY_ONLY = 0;
    const LOG_ANYWAY       = 1000000;

    protected static $container = array();

    protected static $file = 'log';


    protected static $logLevel = 10;

    protected static $logToScreen = true;


    /**
     *
     * Добавить сообщение. Если уровень у сообщения больше чем текущий уровень логгирования, то оно добавляется
     * в очередь на запись. Если нет, то вывести на экран.
     *
     * @static
     * @throws Exception
     * @param string $record
     * @param int $logLevel
     * @return bool
     */
    public static function AddRecord($record = '', $logLevel = 10){
        if(is_array($record)):
            $record = implode(' ', $record);
        endif;

        $record  = trim($record);
        if($record == ''):
            self::AddRecord('Невозможно добавть в лог пустую запись!');
        endif;

        if(true === self::$logToScreen):
            echo date("H:i:s").' '.$record.'<br>'; flush();
        endif;

        if($logLevel >= self::$logLevel):
            self::$container[] = date("H:i:s").' '.$record;
        endif;

        return true;

    }//public static function AddRecord($record = ''){



    /**
     * Сохранить сообщения в файл с текущей датой.
     *
     * @static
     *
     *
     * @return bool
     */
    public static function WriteToLog(){
        global $CFG;
        $date = date("m.d.Y");
        $logFileName = self::$file.'_'.$date;


        $result = (bool) file_put_contents($CFG->logs."/yandex_class/$logFileName.txt", implode(' ', self::$container)."\r\n", FILE_APPEND);
        return $result;
    }//public static function WriteToLog(){




    public  static function setLogLevel($newLogLevel = 10){
        if (!is_int($newLogLevel))
            throw new Exception('Неверный уровень логгирования!');


        self::$logLevel = $newLogLevel;

        return true;
    }// public  static function setLogLevel($newLogLevel = 1){




    public static function setLogFileName($fileName){
        $fileName = trim($fileName);
        if(!$fileName)
            throw new Exception('Пустое имя файла для лога!');

        self::$file = $fileName;

    }//public static function setLogFileName($fileName){



    public static function getLogFileName(){
        return self::$file;
    }

    public static function setLogToScreen($new){
        $old = self::$logToScreen;
        self::$logToScreen = (boolean) $new;

        return $old;
    }//public static function setLogToScreen($new){


    public static function getLogToScreen(){
            return self::$logToScreen;
    }//public static function getLogToScreen(){


}//class logger {


function exception_handler($exception) {
  logger::AddRecord('НЕШТАТНОЕ ЗАВЕРШЕНИЕ РАБОТЫ  ПО ИСКЛЮЧЕНИЮ!!!', logger::LOG_ANYWAY);
  logger::AddRecord($exception->getMessage());
  logger::AddRecord($exception->getTraceAsString());
  logger::WriteToLog();
}//function exception_handler($exception) {

set_exception_handler('exception_handler');

