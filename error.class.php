<?php
class error{
    var $debug;
    public function __construct($message,$args=array()) {
        $this->debug=debug_backtrace();
        echo '<pre>';
        print_r($this->debug);
        echo'</pre>';
        echo 'call error, find mistake: '.$message;
        die();
    }
    public function __destruct() {
         
    }
}
?>
